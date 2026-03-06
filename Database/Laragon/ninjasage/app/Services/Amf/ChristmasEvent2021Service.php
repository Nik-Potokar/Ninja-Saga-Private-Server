<?php

namespace App\Services\Amf;

use App\Models\Character;
use App\Models\CharacterEventData;
use App\Models\CharacterItem;
use App\Models\GameEvent;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ChristmasEvent2021 AMF Service — backs the "Daily Gacha" feature panel.
 *
 * The client (DailyGacha.as) hard-codes the service name as ChristmasEvent2021.
 * Config lives in the GameEvent row with panel = 'DailyGacha'.
 *
 * Gacha coin: material_874
 * Draw costs:
 *   coins  qty=1 →   1 coin,  qty=3 →   3 coins
 *   tokens qty=1 →  50 tokens, qty=3 → 100 tokens, qty=6 → 250 tokens
 *
 * Expected GameEvent data shape:
 * {
 *   "pool_weights": [5, 25, 70],
 *   "pool": {
 *     "top":    ["item_id", ...],
 *     "mid":    ["item_id", ...],
 *     "common": ["item_id", ...]
 *   },
 *   "bonus_rewards": [
 *     { "id": "item_xxx", "req": 10 },
 *     ...
 *   ]
 * }
 */
class ChristmasEvent2021Service
{
    use ValidatesSession;

    private const PANEL              = 'DailyGacha';
    private const EVENT_KEY          = 'daily_gacha';
    private const GACHA_COIN         = 'material_874';
    private const GLOBAL_HISTORY_KEY = 'daily_gacha_global_history';

    // Token costs keyed by qty
    private const TOKEN_COST = [1 => 50, 3 => 100, 6 => 250];
    // Coin costs keyed by qty
    private const COIN_COST  = [1 => 1,  3 => 3];

    // -------------------------------------------------------------------------

    /**
     * Params: sessionKey, charId, accountId
     * Returns: { status: 1, coin: int }
     */
    public function getData($sessionKey, $charId, $accountId)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        if ($this->config() === null) {
            return ['status' => 2, 'result' => 'Daily Gacha is currently inactive.'];
        }

        return [
            'status' => 1,
            'error'  => 0,
            'coin'   => $this->getCoinBalance((int) $charId),
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Params: sessionKey, charId, playType ("coins"|"tokens"), playQty (1|3|6)
     * Returns: { status: 1, rewards: string[], coin: int }
     */
    public function getGachaRewards($sessionKey, $charId, $playType, $playQty)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        $config = $this->config();
        if ($config === null) {
            return ['status' => 2, 'result' => 'Daily Gacha is currently inactive.'];
        }

        $charId  = (int) $charId;
        $playQty = (int) $playQty;

        if (!in_array($playType, ['coins', 'tokens'], true)) {
            return ['status' => 2, 'result' => 'Invalid play type.'];
        }

        $validQtys = $playType === 'coins' ? [1, 3] : [1, 3, 6];
        if (!in_array($playQty, $validQtys, true)) {
            return ['status' => 2, 'result' => 'Invalid draw quantity.'];
        }

        $char = Character::with('user')->find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        // Deduct currency before rolling
        if ($playType === 'coins') {
            $coinCost = self::COIN_COST[$playQty] ?? $playQty;
            $coinItem = CharacterItem::where('character_id', $charId)->where('item_id', self::GACHA_COIN)->first();
            $balance  = $coinItem ? (int) $coinItem->quantity : 0;
            if ($balance < $coinCost) {
                return ['status' => 2, 'result' => "Not enough Gacha Coins (need {$coinCost})."];
            }
            $coinItem->decrement('quantity', $coinCost);
        } else {
            $tokenCost = self::TOKEN_COST[$playQty] ?? ($playQty * 50);
            $user      = $char->user;
            if (!$user || $user->tokens < $tokenCost) {
                return ['status' => 2, 'result' => "Not enough tokens (need {$tokenCost})."];
            }
            $user->tokens -= $tokenCost;
            $user->save();
        }

        $pool      = $config['pool']         ?? [];
        $weights   = $config['pool_weights'] ?? [5, 25, 70];
        $tierNames = ['top', 'mid', 'common'];

        $allTiersEmpty = empty($pool['top']) && empty($pool['mid']) && empty($pool['common']);
        if ($allTiersEmpty) {
            return ['status' => 2, 'result' => 'Gacha pool is not configured yet.'];
        }

        $rewards = [];
        $granter = new RewardGrantService();
        $gender  = (int) $char->gender === 0 ? '0' : '1';

        for ($i = 0; $i < $playQty; $i++) {
            $tier   = $this->rollTier($weights);
            $picked = $this->pickFromPool($pool[$tierNames[$tier]] ?? []);
            if ($picked === null) {
                // Tier was empty — fall back through tiers
                foreach (['common', 'mid', 'top'] as $fallback) {
                    $picked = $this->pickFromPool($pool[$fallback] ?? []);
                    if ($picked !== null) break;
                }
            }
            if ($picked === null) continue;

            $granter->grant($char, $picked);

            // Build display-safe ID: strip :qty suffix, resolve %s gender placeholder
            $displayId = explode(':', $picked)[0];
            $displayId = str_replace('%s', $gender, $displayId);
            $rewards[] = $displayId;
        }

        $char->refresh();

        // Track total spin count and personal history in CharacterEventData
        $eventData      = CharacterEventData::forCharacter($charId, self::EVENT_KEY, 0);
        $extra          = $eventData->extra ?? [];
        $spinCount      = ($extra['spins'] ?? 0) + $playQty;
        $extra['spins'] = $spinCount;

        $personalHistory = $extra['history'] ?? [];
        foreach ($rewards as $reward) {
            array_unshift($personalHistory, [
                'id'          => $charId,
                'name'        => $char->name,
                'level'       => (int) $char->level,
                'reward'      => $reward,
                'spin'        => $spinCount,
                'obtained_at' => now()->format('Y-m-d H:i'),
                'currency'    => $playType === 'tokens' ? 1 : 0,
            ]);
        }
        $extra['history']  = array_slice($personalHistory, 0, 50);
        $eventData->extra  = $extra;
        $eventData->save();

        // Push first reward to global history cache
        if (!empty($rewards)) {
            $this->pushGlobalHistory([
                'id'          => $charId,
                'name'        => $char->name,
                'level'       => (int) $char->level,
                'reward'      => $rewards[0],
                'spin'        => $spinCount,
                'obtained_at' => now()->format('Y-m-d H:i'),
                'currency'    => $playType === 'tokens' ? 1 : 0,
            ]);
        }

        Log::info("AMF ChristmasEvent2021.getGachaRewards: Char {$charId} Type {$playType} Qty {$playQty} Rewards " . implode(',', $rewards));

        return [
            'status'  => 1,
            'error'   => 0,
            'rewards' => array_values($rewards),
            'coin'    => $this->getCoinBalance($charId),
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Params: sessionKey, charId, accountId
     * Returns: { status: 1, data: [{id, req, claimed}, ...], total_spins: int }
     */
    public function getBonusRewards($sessionKey, $charId, $accountId)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        $config = $this->config();
        if ($config === null) {
            return ['status' => 2, 'result' => 'Daily Gacha is currently inactive.'];
        }

        $charId    = (int) $charId;
        $eventData = CharacterEventData::forCharacter($charId, self::EVENT_KEY, 0);
        $extra     = $eventData->extra ?? [];
        $spinCount = $extra['spins'] ?? 0;
        $claimed   = $extra['bonus_claimed'] ?? [];

        $bonusRewards = $config['bonus_rewards'] ?? [];
        $data = [];
        foreach ($bonusRewards as $i => $entry) {
            $data[] = [
                'id'      => $entry['id'],
                'req'     => $entry['req'],
                'claimed' => in_array($i, $claimed, true),
            ];
        }

        return [
            'status'      => 1,
            'error'       => 0,
            'data'        => array_values($data),
            'total_spins' => $spinCount,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Params: sessionKey, charId, index
     * Returns: { status: 1, reward: string[] }
     */
    public function claimBonusGachaRewards($sessionKey, $charId, $index)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        $config = $this->config();
        if ($config === null) {
            return ['status' => 2, 'result' => 'Daily Gacha is currently inactive.'];
        }

        $charId    = (int) $charId;
        $index     = (int) $index;
        $eventData = CharacterEventData::forCharacter($charId, self::EVENT_KEY, 0);
        $extra     = $eventData->extra ?? [];
        $spinCount = $extra['spins'] ?? 0;
        $claimed   = $extra['bonus_claimed'] ?? [];

        $bonusRewards = $config['bonus_rewards'] ?? [];
        if (!isset($bonusRewards[$index])) {
            return ['status' => 2, 'result' => 'Invalid bonus reward index.'];
        }

        $entry = $bonusRewards[$index];

        if ($spinCount < (int) $entry['req']) {
            return ['status' => 2, 'result' => 'Not enough spins to claim this reward.'];
        }

        if (in_array($index, $claimed, true)) {
            return ['status' => 2, 'result' => 'Bonus reward already claimed.'];
        }

        $char = Character::with('user')->find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $rewardStr = $entry['id'];
        (new RewardGrantService())->grant($char, $rewardStr);

        $claimed[]              = $index;
        $extra['bonus_claimed'] = $claimed;
        $eventData->extra       = $extra;
        $eventData->save();

        $displayId = explode(':', $rewardStr)[0];
        $displayId = str_replace('%s', $char->gender == 0 ? '0' : '1', $displayId);

        Log::info("AMF ChristmasEvent2021.claimBonusGachaRewards: Char {$charId} Index {$index} Reward {$rewardStr}");

        return [
            'status' => 1,
            'error'  => 0,
            'reward' => [$displayId],
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Params: sessionKey, charId
     * Returns: { status: 1, top: [], mid: [], common: [] }
     */
    public function getRewardList($sessionKey, $charId)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        $config = $this->config();
        if ($config === null) {
            return ['status' => 2, 'result' => 'Daily Gacha is currently inactive.'];
        }

        $pool   = $config['pool'] ?? [];
        $gender = (int) Character::find((int) $charId)?->gender === 0 ? '0' : '1';

        // Resolve gender placeholder, strip :qty suffix, and remove broken entries
        // (entries with no trailing identifier after the type prefix, e.g. "gold_" or "tokens_")
        $resolve = function (array $items) use ($gender): array {
            $result = [];
            foreach ($items as $id) {
                $base = str_replace('%s', $gender, explode(':', $id)[0]);
                // Skip entries whose entire value is just a prefix with nothing after (e.g. "gold_", "tokens_")
                if (preg_match('/^(gold|tokens|xp|tp|ss)_$/', $base)) {
                    continue;
                }
                $result[] = $base;
            }
            return array_values($result);
        };

        // The "Biggest Prize" section has exactly 2 UI slots (IconMc0_0, IconMc0_1).
        // Returning more causes a null-reference crash in showRewardsTop().
        $topItems = array_slice($resolve($pool['top'] ?? []), 0, 2);

        return [
            'status' => 1,
            'error'  => 0,
            'top'    => $topItems,
            'mid'    => $resolve($pool['mid']    ?? []),
            'common' => $resolve($pool['common'] ?? []),
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Params: charId, sessionKey
     * Returns: { status: 1, histories: array }
     */
    public function getPersonalGachaHistory($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        if ($this->config() === null) {
            return ['status' => 2, 'result' => 'Daily Gacha is currently inactive.'];
        }

        $extra     = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, 0)->extra ?? [];
        $histories = $extra['history'] ?? [];

        return [
            'status'    => 1,
            'error'     => 0,
            'histories' => array_values($histories),
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

        if ($this->config() === null) {
            return ['status' => 2, 'result' => 'Daily Gacha is currently inactive.'];
        }

        return [
            'status'    => 1,
            'error'     => 0,
            'histories' => Cache::get(self::GLOBAL_HISTORY_KEY, []),
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

    private function pushGlobalHistory(array $entry): void
    {
        $history = Cache::get(self::GLOBAL_HISTORY_KEY, []);
        array_unshift($history, $entry);
        Cache::put(self::GLOBAL_HISTORY_KEY, array_slice($history, 0, 50), 86400 * 7);
    }

    private function config(): ?array
    {
        $event = GameEvent::where('panel', self::PANEL)->where('active', true)->first();
        if (!$event) return null;
        return $event->data ?? [];
    }
}