<?php

namespace App\Services\Amf;

use App\Models\Character;
use App\Models\CharacterEventData;
use App\Models\CharacterItem;
use App\Models\GameEvent;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * DragonHuntEvent AMF Service
 *
 * DragonHunt is data-driven from the client-side GameData (boss lists, names, levels).
 * The server only handles:
 *   - startBattle  : validates hash, charges cost, issues battle code + capture range
 *   - finishBattle : validates code, grants rewards for the captured boss
 *   - buyMaterial  : deducts tokens, adds item_54 (sushi) or item_52 (seal) to inventory
 *
 * Admin GameEvent config (data JSON, panel = "DragonHunt"):
 * {
 *   "material_token_cost": 10,        // tokens per material bought
 *   "normal_mode_gold_cost": 250000,  // gold cost for Normal mode
 *   "easy_mode_token_cost": 100,      // token cost for Easy mode
 *   "rewards_per_boss": {             // map bossId → reward strings
 *     "enemy_dragon_1": ["material_456:3", "gold_50000"],
 *     "enemy_dragon_2": ["material_456:5", "gold_75000"]
 *   },
 *   "capture_range": {                // capture HP% window per mode
 *     "0": [0, 5],   "1": [0, 15],  "2": [0, 25]
 *   },
 *   "gacha": {
 *     "coin_cost":    [1, 2],         // cost in material_773 for qty [1, 2]
 *     "token_cost":   [25, 50, 250],  // cost in tokens for qty [1, 2, 6]
 *     "pool_weights": [5, 25, 70],    // % weight for [top, mid, common] tiers
 *     "pool": {
 *       "top":    ["skill_xxx", "wpn_xxx"],
 *       "mid":    ["set_xxx",   "material_xxx:5"],
 *       "common": ["material_xxx:3", "gold_50000"]
 *     }
 *   }
 * }
 */
class DragonHuntEventService
{
    use ValidatesSession;

    private const PANEL               = 'DragonHunt';
    private const GACHA_PANEL         = 'DragonGacha';
    private const EVENT_KEY           = 'dragon_hunt';
    private const MATERIAL_TOKEN_COST = 10;
    private const SUSHI               = 'item_54';
    private const SEAL                = 'item_52';
    private const GACHA_COIN          = 'material_773';
    private const HISTORY_CACHE_KEY   = 'dragon_gacha_history';

    // Capture HP% windows per mode: [start%, end%]
    private const CAPTURE_RANGES = [
        0 => [0, 5],   // Hard
        1 => [0, 15],  // Normal
        2 => [0, 25],  // Easy
    ];

    // -------------------------------------------------------------------------

    /**
     * Battle.as params: charId, bossId, mode, agility, enemyStats, hash, sessionKey
     */
    public function startBattle($charId, $bossId, $mode, $agility, $enemyStats, $hash, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF DragonHuntEvent.startBattle: Char $charId Boss $bossId Mode $mode");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Dragon Hunt event is currently inactive.'];
        }

        $mode = (int) $mode;
        $char = Character::with('user')->find((int) $charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        // Validate client hash: sha256(charId + bossId + mode + enemyStats + agility)
        $expectedHash = hash('sha256', $charId . $bossId . $mode . $enemyStats . $agility);
        if (!hash_equals($expectedHash, (string) $hash)) {
            Log::warning("DragonHunt startBattle hash mismatch for Char $charId");
            return ['status' => 2, 'result' => 'Security check failed.'];
        }

        // Charge mode costs
        $goldCost  = (int) ($config['normal_mode_gold_cost'] ?? 250000);
        $tokenCost = (int) ($config['easy_mode_token_cost'] ?? 100);

        if ($mode === 1) {
            if ($char->gold < $goldCost) {
                return ['status' => 2, 'result' => "Not enough gold (need {$goldCost})."];
            }
            $char->gold -= $goldCost;
            $char->save();
        } elseif ($mode === 2) {
            $user = $char->user;
            if (!$user || $user->tokens < $tokenCost) {
                return ['status' => 2, 'result' => "Not enough tokens (need {$tokenCost})."];
            }
            $user->tokens -= $tokenCost;
            $user->save();
        }

        $ranges = $config['capture_range'][$mode] ?? self::CAPTURE_RANGES[$mode] ?? [0, 25];
        [$n1, $n2] = $ranges;

        $code = Str::random(16);

        Cache::put("dh_battle_{$charId}", [
            'code'    => $code,
            'boss_id' => $bossId,
            'mode'    => $mode,
            'config'  => $config,
        ], 1800);

        // Server hash client verifies: sha256(bossId + code + charId + n1 + n2)
        $responseHash = hash('sha256', $bossId . $code . $charId . $n1 . $n2);

        return [
            'status' => 1,
            'error'  => 0,
            'code'   => $code,
            'hash'   => $responseHash,
            'n1'     => $n1,
            'n2'     => $n2,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Battle.as params: charId, bossId, battleCode, hash, totalDamage, sessionKey, unknown, mode, capturedAt
     */
    public function finishBattle($charId, $bossId, $battleCode, $hash, $totalDamage, $sessionKey, $unknown, $mode, $capturedAt)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF DragonHuntEvent.finishBattle: Char $charId Boss $bossId CapturedAt $capturedAt");

        $cached = Cache::get("dh_battle_{$charId}");
        if (!$cached || $cached['code'] !== $battleCode) {
            return ['status' => 2, 'result' => 'Invalid battle session.'];
        }
        Cache::forget("dh_battle_{$charId}");

        $char   = Character::with('user')->find((int) $charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $config    = $cached['config'];
        $captured  = (int) $capturedAt >= 0;
        $rewardsMap = $config['rewards_per_boss'] ?? [];
        $rewards   = $rewardsMap[$bossId] ?? ($config['rewards_default'] ?? []);

        $granted = [];
        $levelUp = false;

        if ($captured) {
            $granter = new RewardGrantService();
            foreach ($rewards as $rewardStr) {
                $levelUp = $granter->grant($char, (string) $rewardStr) || $levelUp;
                $granted[] = $rewardStr;
            }

            CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, 0)->increment('battles');
            $char->refresh();
        }

        return [
            'status'   => 1,
            'error'    => 0,
            'result'   => $granted,
            'level'    => (int) $char->level,
            'xp'       => (int) $char->xp,
            'level_up' => $levelUp,
            'captured' => $captured,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Params: charId, sessionKey, materialId, amount
     */
    public function buyMaterial($charId, $sessionKey, $materialId, $amount)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF DragonHuntEvent.buyMaterial: Char $charId Material $materialId Amount $amount");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Dragon Hunt event is currently inactive.'];
        }

        $amount = max(1, (int) $amount);

        // Only allow the two valid material IDs.
        if (!in_array($materialId, [self::SUSHI, self::SEAL], true)) {
            return ['status' => 2, 'result' => 'Invalid material.'];
        }

        $tokenCost = (int) ($config['material_token_cost'] ?? self::MATERIAL_TOKEN_COST);
        $totalCost = $tokenCost * $amount;

        $char = Character::with('user')->find((int) $charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $user = $char->user;
        if (!$user || $user->tokens < $totalCost) {
            return ['status' => 2, 'result' => "Not enough tokens (need {$totalCost})."];
        }

        $user->tokens -= $totalCost;
        $user->save();

        $item = CharacterItem::firstOrCreate(
            ['character_id' => $char->id, 'item_id' => $materialId],
            ['quantity' => 0, 'category' => 'material']
        );
        $item->increment('quantity', $amount);

        return [
            'status' => 1,
            'error'  => 0,
            'tokens' => (int) $user->tokens,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Params: charId, sessionKey, accountId
     * Returns: { status: 1, coin: <int> }
     */
    public function getGachaData($charId, $sessionKey, $accountId)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        $config = $this->gachaConfig();
        if ($config === null) {
            return ['status' => 2, 'result' => 'Dragon Gacha is currently inactive.'];
        }

        return [
            'status' => 1,
            'error'  => 0,
            'coin'   => $this->getCoinBalance((int) $charId),
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Params: charId, sessionKey, playType ("coins"|"tokens"), playQty (1|2|6)
     * Returns: { status: 1, rewards: string[], coin: int }
     *
     * Cost mapping (mirrors client PRICE_COINS / PRICE_TOKENS constants):
     *   coins  qty=1 → coin_cost[0],  qty=2 → coin_cost[1]
     *   tokens qty=1 → token_cost[0], qty=2 → token_cost[1], qty=6 → token_cost[2]
     */
    public function getGachaRewards($charId, $sessionKey, $playType, $playQty)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        $config = $this->gachaConfig();
        if ($config === null) {
            return ['status' => 2, 'result' => 'Dragon Gacha is currently inactive.'];
        }

        $charId  = (int) $charId;
        $playQty = (int) $playQty;

        if (!in_array($playType, ['coins', 'tokens'], true)) {
            return ['status' => 2, 'result' => 'Invalid play type.'];
        }

        $validQtys = $playType === 'coins' ? [1, 2] : [1, 2, 6];
        if (!in_array($playQty, $validQtys, true)) {
            return ['status' => 2, 'result' => 'Invalid draw quantity.'];
        }

        $char = Character::with('user')->find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        // $config is already the flat gacha config returned by gachaConfig()
        // Expected 'draws' structure:
        //   "draws": {
        //     "normal":         { "qty": 1, "coin_cost": 1,  "token_cost": 25  },
        //     "advanced":       { "qty": 2, "coin_cost": 2,  "token_cost": 50  },
        //     "advanced_bonus": { "qty": 6,                  "token_cost": 250 }
        //   }
        $draws     = $config['draws']       ?? [];
        $pool      = $config['pool']        ?? [];
        $weights   = $config['pool_weights'] ?? [5, 25, 70];
        $tierNames = ['top', 'mid', 'common'];

        // Map qty → draw config key
        $drawKey = match (true) {
            $playQty === 6 => 'advanced_bonus',
            $playQty === 2 => 'advanced',
            default        => 'normal',
        };
        $drawCfg = $draws[$drawKey] ?? [];

        // Validate pool before touching currency
        $allTiersEmpty = empty($pool['top']) && empty($pool['mid']) && empty($pool['common']);
        if ($allTiersEmpty) {
            return ['status' => 2, 'result' => 'Gacha is not configured yet.'];
        }

        if ($playType === 'coins') {
            $coinCost = (int) ($drawCfg['coin_cost'] ?? $playQty);

            $coinItem     = CharacterItem::where('character_id', $charId)->where('item_id', self::GACHA_COIN)->first();
            $currentCoins = $coinItem ? (int) $coinItem->quantity : 0;
            if ($currentCoins < $coinCost) {
                return ['status' => 2, 'result' => "Not enough Dragon Coins (need {$coinCost})."];
            }
            $coinItem->decrement('quantity', $coinCost);
        } else {
            $tokenCost = (int) ($drawCfg['token_cost'] ?? ($playQty * 25));

            $user = $char->user;
            if (!$user || $user->tokens < $tokenCost) {
                return ['status' => 2, 'result' => "Not enough tokens (need {$tokenCost})."];
            }
            $user->tokens -= $tokenCost;
            $user->save();
        }

        // Roll rewards
        // $rewards contains display-safe item IDs (no :qty suffix, %s resolved)
        // so NinjaSage.loadItemIcon and Character.addRewards work correctly.
        $rewards = [];
        $granter = new RewardGrantService();
        $gender  = (int) $char->gender === 0 ? '0' : '1';

        for ($i = 0; $i < $playQty; $i++) {
            $tier   = $this->rollTier($weights);
            $picked = $this->pickFromPool($pool[$tierNames[$tier]] ?? []);
            if ($picked === null) {
                // Tier was empty — fall back to common
                foreach (['common', 'mid', 'top'] as $fallback) {
                    $picked = $this->pickFromPool($pool[$fallback] ?? []);
                    if ($picked !== null) break;
                }
            }
            if ($picked === null) continue;

            $granter->grant($char, $picked);

            // Build display-safe ID: strip :qty, resolve %s gender
            $displayId = explode(':', $picked)[0];
            $displayId = str_replace('%s', $gender, $displayId);
            $rewards[] = $displayId;
        }

        $char->refresh();

        $eventData            = CharacterEventData::forCharacter($charId, self::EVENT_KEY, 0);
        $extra                = $eventData->extra ?? [];
        $spinCount            = ($extra['gacha_spins'] ?? 0) + $playQty;
        $extra['gacha_spins'] = $spinCount;
        $eventData->extra     = $extra;
        $eventData->save();

        if (!empty($rewards)) {
            $this->pushHistory([
                'id'          => $charId,
                'name'        => $char->name,
                'level'       => (int) $char->level,
                'reward'      => $rewards[0],
                'spin'        => $spinCount,
                'obtained_at' => now()->format('Y-m-d H:i'),
            ]);
        }

        Log::info("AMF DragonHuntEvent.getGachaRewards: Char $charId Type $playType Qty $playQty Rewards " . implode(',', $rewards));

        return [
            'status'  => 1,
            'error'   => 0,
            'rewards' => array_values($rewards),
            'coin'    => $this->getCoinBalance($charId),
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Params: charId, sessionKey
     * Returns: { status: 1, histories: array }
     */
    public function getGlobalGachaHistory($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        if ($this->gachaConfig() === null) {
            return ['status' => 2, 'result' => 'Dragon Gacha is currently inactive.'];
        }

        return [
            'status'    => 1,
            'error'     => 0,
            'histories' => Cache::get(self::HISTORY_CACHE_KEY, []),
        ];
    }

    // -------------------------------------------------------------------------

    private function getCoinBalance(int $charId): int
    {
        $item = CharacterItem::where('character_id', $charId)->where('item_id', self::GACHA_COIN)->first();
        return $item ? (int) $item->quantity : 0;
    }

    private function rollTier(array $weights): int
    {
        $total  = array_sum($weights);
        $roll   = mt_rand(1, max(1, $total));
        $cumsum = 0;
        foreach ($weights as $i => $w) {
            $cumsum += $w;
            if ($roll <= $cumsum) {
                return $i;
            }
        }
        return count($weights) - 1;
    }

    private function pickFromPool(array $pool): ?string
    {
        if (empty($pool)) return null;
        return $pool[array_rand($pool)];
    }

    private function pushHistory(array $entry): void
    {
        $history = Cache::get(self::HISTORY_CACHE_KEY, []);
        array_unshift($history, $entry);
        Cache::put(self::HISTORY_CACHE_KEY, array_slice($history, 0, 50), 86400 * 7);
    }

    // -------------------------------------------------------------------------

    private function config(): ?array
    {
        $event = GameEvent::where('panel', self::PANEL)->where('active', true)->first();
        if (!$event) return null;
        return $event->data ?? [];
    }

    /**
     * Gacha-specific config lives in the 'DragonGacha' GameEvent row so it
     * can be toggled and edited independently of the Dragon Hunt battle event.
     * Falls back to a 'gacha' sub-key inside the DragonHunt config for
     * backwards-compatibility if someone put it there instead.
     */
    private function gachaConfig(): ?array
    {
        $event = GameEvent::where('panel', self::GACHA_PANEL)->where('active', true)->first();
        if ($event) {
            return $event->data ?? [];
        }

        // Fallback: 'gacha' section inside the DragonHunt event
        $battleConfig = $this->config();
        if (isset($battleConfig['gacha'])) {
            return $battleConfig['gacha'];
        }

        return null;
    }
}