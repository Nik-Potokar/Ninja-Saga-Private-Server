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
 * ChristmasEvent2025 AMF Service  (in-client panel: "ChristmasMenu")
 *
 * Backs the "Yuki Onna: Eternal Winter" seasonal event.
 * Two energy pools:
 *   - Battle energy:  10 hearts, 1 per fight, refill 50 tokens.
 *   - Minigame energy: 8 hearts, 1 per Pet Frenzy run, same refill cost.
 * 3 selectable bosses (bosses[].id is an array of enemy IDs).
 * 8 milestone rewards keyed off total battles won.
 * New-Year section: one-time free item claim (NewYear2026.claim calls a separate service).
 * Christmas Gacha is handled by a separate ChristmasGacha panel / service.
 *
 * Admin GameEvent config (data JSON, panel = "ChristmasMenu"):
 * {
 *   "bosses": [
 *     {
 *       "id": ["ene_2117"],
 *       "name": "Yuki Onna Warrior",
 *       "description": "...",
 *       "levels": [0, 5],
 *       "xp":   "level * 2500 / 60",
 *       "gold": "level * 2500 / 60",
 *       "rewards": ["material_2226", "material_2228", "material_2231"],
 *       "background": "mission_1065"
 *     }
 *   ],
 *   "minigame": ["material_2230", "material_2231"],
 *   "new_year":  ["hair_2364_%s", "set_2405_%s"],
 *   "milestone_battle": [
 *     { "id": "gold_100000",   "quantity": 1,  "requirement": 10  },
 *     { "id": "material_2229", "quantity": 10, "requirement": 50  },
 *     { "id": "hair_2362_%s",  "quantity": 1,  "requirement": 100 },
 *     ...
 *   ],
 *   "rewards_preview": { "hair": [], "set": [], "back": [], "weapon": [], "skill": [] }
 * }
 */
class ChristmasEvent2025Service
{
    use ValidatesSession;

    private const PANEL               = 'ChristmasMenu';
    private const EVENT_KEY           = 'christmas_2025';
    private const EVENT_KEY_MINIGAME  = 'christmas_2025_mini';
    private const EVENT_KEY_GACHA     = 'christmas_2025_gacha';
    private const ENERGY_MAX          = 10;   // battle hearts (SWF hardcodes 10)
    private const MINIGAME_ENERGY_MAX = 8;    // minigame hearts (SWF hardcodes 8)
    private const ENERGY_COST         = 1;
    private const REFILL_TOKEN_COST   = 50;   // SWF: REFILL_PRICE = 50
    private const MILESTONE_COUNT     = 8;
    private const GACHA_COIN          = 'material_2231';
    private const GLOBAL_GACHA_KEY    = 'christmas_2025_gacha_global';

    // Token costs keyed by qty (matches ChristmasGacha.as PRICE_TOKENS = [20, 50, 100])
    private const GACHA_TOKEN_COST = [1 => 20, 3 => 50, 6 => 100];
    // Coin costs keyed by qty (matches ChristmasGacha.as PRICE_COINS = [1, 3])
    private const GACHA_COIN_COST  = [1 => 1,  3 => 3];

    // -------------------------------------------------------------------------
    // Battle flow
    // -------------------------------------------------------------------------

    public function getBattleData($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ChristmasEvent2025.getBattleData: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Christmas event is currently inactive.'];
        }

        $eventData = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, self::ENERGY_MAX);

        return [
            'status' => 1,
            'error'  => 0,
            'energy' => $eventData->energy,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Params: charId, bossId, agility, enemyStats, hash, sessionKey
     * Client hash = sha256(charId + bossId + enemyStats + agility)
     * Server response hash = sha256(bossId + code + charId)  — verified by client.
     */
    public function startBattle($charId, $bossId, $agility, $enemyStats, $hash, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ChristmasEvent2025.startBattle: Char $charId Boss $bossId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Christmas event is currently inactive.'];
        }

        $eventData = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, self::ENERGY_MAX);

        if ($eventData->energy < self::ENERGY_COST) {
            return ['status' => 2, 'result' => 'Not enough energy. Please refill to continue.'];
        }

        $boss = $this->findBoss($config, (string) $bossId);
        if (!$boss) {
            Log::warning("ChristmasEvent2025 startBattle: unknown bossId '$bossId' for Char $charId");
            return ['status' => 2, 'result' => 'Unknown boss.'];
        }

        // Validate client hash: sha256(charId + bossId + enemyStats + agility)
        $expectedHash = hash('sha256', $charId . $bossId . $enemyStats . $agility);
        if (!hash_equals($expectedHash, (string) $hash)) {
            Log::warning("ChristmasEvent2025 startBattle hash mismatch for Char $charId");
            return ['status' => 2, 'result' => 'Security check failed.'];
        }

        $code = Str::random(16);

        $eventData->energy -= self::ENERGY_COST;
        $eventData->save();

        Cache::put("xmas_battle_{$charId}", [
            'code'    => $code,
            'boss_id' => $bossId,
            'boss'    => $boss,
            'config'  => $config,
        ], 1800);

        // Server hash verified by client: sha256(bossId + code + charId)
        $responseHash = hash('sha256', $bossId . $code . $charId);

        return [
            'status' => 1,
            'error'  => 0,
            'code'   => $code,
            'hash'   => $responseHash,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Called by Battle.as after combat ends.
     * Params: charId, bossId, battleCode, totalDamage, hash, win, sessionKey
     * Client hash = sha256(charId + bossId + battleCode + totalDamage + win)
     */
    public function finishBattle($charId, $bossId, $battleCode, $totalDamage, $hash, $win, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ChristmasEvent2025.finishBattle: Char $charId Win $win");

        $cached = Cache::get("xmas_battle_{$charId}");
        if (!$cached || $cached['code'] !== $battleCode) {
            return ['status' => 2, 'result' => 'Invalid battle session.'];
        }
        Cache::forget("xmas_battle_{$charId}");

        $char = Character::with('user')->find((int) $charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $boss    = $cached['boss'];
        $rewards = $boss['rewards'] ?? [];
        $granted = [];
        $levelUp = false;

        if ((int) $win === 1) {
            $granter = new RewardGrantService();
            foreach ($rewards as $rewardStr) {
                $levelUp = $granter->grant($char, (string) $rewardStr) || $levelUp;
                $granted[] = $rewardStr;
            }

            CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, self::ENERGY_MAX)->increment('battles');
            $char->refresh();
        }

        return [
            'status'   => 1,
            'error'    => 0,
            'result'   => $granted,
            'level'    => (int) $char->level,
            'xp'       => (int) $char->xp,
            'level_up' => $levelUp,
        ];
    }

    // -------------------------------------------------------------------------

    public function refillEnergy($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ChristmasEvent2025.refillEnergy: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Christmas event is currently inactive.'];
        }

        $refillCost = (int) ($config['refill_token_cost'] ?? self::REFILL_TOKEN_COST);

        $char = Character::with('user')->find((int) $charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $user = $char->user;
        if (!$user || $user->tokens < $refillCost) {
            return ['status' => 2, 'result' => "Not enough tokens (need {$refillCost})."];
        }

        $user->tokens -= $refillCost;
        $user->save();

        $eventData         = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, self::ENERGY_MAX);
        $eventData->energy = self::ENERGY_MAX;
        $eventData->save();

        return [
            'status' => 1,
            'error'  => 0,
            'energy' => self::ENERGY_MAX,
        ];
    }

    // -------------------------------------------------------------------------
    // Milestone flow
    // -------------------------------------------------------------------------

    public function getBonusRewards($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ChristmasEvent2025.getBonusRewards: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Christmas event is currently inactive.'];
        }

        $eventData = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, self::ENERGY_MAX);

        $rewardsState = [];
        for ($i = 0; $i < self::MILESTONE_COUNT; $i++) {
            $rewardsState[$i] = $eventData->hasMilestoneClaimed($i);
        }

        return [
            'status'    => 1,
            'error'     => 0,
            'milestone' => $eventData->battles,
            'rewards'   => $rewardsState,
        ];
    }

    // -------------------------------------------------------------------------

    public function claimBonusRewards($charId, $sessionKey, $rewardIndex)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ChristmasEvent2025.claimBonusRewards: Char $charId Index $rewardIndex");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Christmas event is currently inactive.'];
        }

        $rewardIndex = (int) $rewardIndex;
        $milestones  = $this->milestones($config);

        if (!isset($milestones[$rewardIndex])) {
            return ['status' => 2, 'result' => 'Invalid milestone index.'];
        }

        $eventData = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, self::ENERGY_MAX);
        $milestone = $milestones[$rewardIndex];

        if ($eventData->battles < (int) ($milestone['requirement'] ?? 0)) {
            return ['status' => 2, 'result' => 'Milestone requirement not met yet.'];
        }

        if ($eventData->hasMilestoneClaimed($rewardIndex)) {
            return ['status' => 2, 'result' => 'Milestone already claimed.'];
        }

        $char = Character::with('user')->find((int) $charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $rewardId = $milestone['id'] ?? $milestone['reward'] ?? '';
        (new RewardGrantService())->grant($char, (string) $rewardId);
        $eventData->claimMilestone($rewardIndex);

        return [
            'status' => 1,
            'error'  => 0,
            'reward' => [$rewardId],
        ];
    }

    // -------------------------------------------------------------------------
    // Minigame (Pet Frenzy) flow
    // -------------------------------------------------------------------------

    public function getMinigameData($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ChristmasEvent2025.getMinigameData: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Christmas event is currently inactive.'];
        }

        $eventData = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY_MINIGAME, self::MINIGAME_ENERGY_MAX);

        return [
            'status' => 1,
            'error'  => 0,
            'energy' => $eventData->energy,
        ];
    }

    // -------------------------------------------------------------------------

    public function startMinigame($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ChristmasEvent2025.startMinigame: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Christmas event is currently inactive.'];
        }

        $eventData = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY_MINIGAME, self::MINIGAME_ENERGY_MAX);

        if ($eventData->energy < self::ENERGY_COST) {
            return ['status' => 2, 'result' => 'Not enough energy. Please refill to continue.'];
        }

        $code = Str::random(16);

        $eventData->energy -= self::ENERGY_COST;
        $eventData->save();

        Cache::put("xmas_mini_{$charId}", [
            'code'   => $code,
            'config' => $config,
        ], 3600);

        return [
            'status' => 1,
            'error'  => 0,
            'code'   => $code,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Called by PetFrenzy.as when the minigame session ends with a win.
     * Params: charId, sessionKey, score, hash
     * Client hash = sha256(charId + "|" + sessionKey + "|" + score + "|" + battleCode)
     */
    public function finishMinigame($charId, $sessionKey, $score, $hash)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ChristmasEvent2025.finishMinigame: Char $charId Score $score");

        $cached = Cache::get("xmas_mini_{$charId}");
        if (!$cached) {
            return ['status' => 2, 'result' => 'Invalid minigame session.'];
        }

        // Validate client hash: sha256(charId + "|" + sessionKey + "|" + score + "|" + code)
        $expectedHash = hash('sha256', $charId . '|' . $sessionKey . '|' . $score . '|' . $cached['code']);
        if (!hash_equals($expectedHash, (string) $hash)) {
            Log::warning("ChristmasEvent2025 finishMinigame hash mismatch for Char $charId");
            return ['status' => 2, 'result' => 'Security check failed.'];
        }

        Cache::forget("xmas_mini_{$charId}");

        $char = Character::with('user')->find((int) $charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $config  = $cached['config'];
        $rewards = $config['minigame'] ?? [];
        $granted = [];

        $granter = new RewardGrantService();
        foreach ($rewards as $rewardStr) {
            $granter->grant($char, (string) $rewardStr);
            $granted[] = explode(':', $rewardStr)[0];
        }

        $currentEnergy = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY_MINIGAME, self::MINIGAME_ENERGY_MAX)->energy;

        return [
            'status'         => 1,
            'error'          => 0,
            'rewards'        => array_values($granted),
            'current_energy' => $currentEnergy,
        ];
    }

    // -------------------------------------------------------------------------

    public function refillMinigameEnergy($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ChristmasEvent2025.refillMinigameEnergy: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Christmas event is currently inactive.'];
        }

        $refillCost = (int) ($config['refill_token_cost'] ?? self::REFILL_TOKEN_COST);

        $char = Character::with('user')->find((int) $charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $user = $char->user;
        if (!$user || $user->tokens < $refillCost) {
            return ['status' => 2, 'result' => "Not enough tokens (need {$refillCost})."];
        }

        $user->tokens -= $refillCost;
        $user->save();

        $eventData         = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY_MINIGAME, self::MINIGAME_ENERGY_MAX);
        $eventData->energy = self::MINIGAME_ENERGY_MAX;
        $eventData->save();

        return [
            'status' => 1,
            'error'  => 0,
            'energy' => self::MINIGAME_ENERGY_MAX,
        ];
    }

    // -------------------------------------------------------------------------
    // Gacha flow  (ChristmasGacha panel → ChristmasEvent2025.*)
    // -------------------------------------------------------------------------

    /**
     * Params: charId, sessionKey, accountId
     * Returns: { status: 1, coin: int }
     */
    public function getGachaData($charId, $sessionKey, $accountId)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ChristmasEvent2025.getGachaData: Char $charId");

        if (!$this->config()) {
            return ['status' => 2, 'result' => 'Christmas event is currently inactive.'];
        }

        return [
            'status' => 1,
            'error'  => 0,
            'coin'   => $this->getGachaCoinBalance((int) $charId),
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Params: charId, sessionKey, playType ("coins"|"tokens"), playQty (1|3|6)
     * Returns: { status: 1, rewards: string[], coin: int }
     */
    public function getGachaRewards($charId, $sessionKey, $playType, $playQty)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ChristmasEvent2025.getGachaRewards: Char $charId Type $playType Qty $playQty");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Christmas event is currently inactive.'];
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

        // Deduct currency
        if ($playType === 'coins') {
            $coinCost = self::GACHA_COIN_COST[$playQty] ?? $playQty;
            $coinItem = CharacterItem::where('character_id', $charId)->where('item_id', self::GACHA_COIN)->first();
            $balance  = $coinItem ? (int) $coinItem->quantity : 0;
            if ($balance < $coinCost) {
                return ['status' => 2, 'result' => "Not enough Gacha Coins (need {$coinCost})."];
            }
            $coinItem->decrement('quantity', $coinCost);
        } else {
            $tokenCost = self::GACHA_TOKEN_COST[$playQty] ?? ($playQty * 20);
            $user      = $char->user;
            if (!$user || $user->tokens < $tokenCost) {
                return ['status' => 2, 'result' => "Not enough tokens (need {$tokenCost})."];
            }
            $user->tokens -= $tokenCost;
            $user->save();
        }

        $gacha    = $config['gacha'] ?? [];
        $weights  = $gacha['pool_weights'] ?? [5, 25, 70];
        $pool     = ['top' => $gacha['top'] ?? [], 'mid' => $gacha['mid'] ?? [], 'common' => $gacha['common'] ?? []];

        if (empty($pool['top']) && empty($pool['mid']) && empty($pool['common'])) {
            return ['status' => 2, 'result' => 'Gacha pool is not configured yet.'];
        }

        $rewards = [];
        $granter = new RewardGrantService();
        $gender  = (int) $char->gender === 0 ? '0' : '1';
        $tierNames = ['top', 'mid', 'common'];

        for ($i = 0; $i < $playQty; $i++) {
            $tier   = $this->rollTier($weights);
            $picked = $this->pickFromPool($pool[$tierNames[$tier]] ?? []);
            if ($picked === null) {
                foreach (['common', 'mid', 'top'] as $fallback) {
                    $picked = $this->pickFromPool($pool[$fallback] ?? []);
                    if ($picked !== null) break;
                }
            }
            if ($picked === null) continue;

            $granter->grant($char, $picked);
            $displayId = str_replace('%s', $gender, explode(':', $picked)[0]);
            $rewards[] = $displayId;
        }

        $char->refresh();

        // Track spin count and history
        $eventData      = CharacterEventData::forCharacter($charId, self::EVENT_KEY_GACHA, 0);
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

        if (!empty($rewards)) {
            $this->pushGlobalGachaHistory([
                'id'          => $charId,
                'name'        => $char->name,
                'level'       => (int) $char->level,
                'reward'      => $rewards[0],
                'spin'        => $spinCount,
                'obtained_at' => now()->format('Y-m-d H:i'),
                'currency'    => $playType === 'tokens' ? 1 : 0,
            ]);
        }

        return [
            'status'  => 1,
            'error'   => 0,
            'rewards' => array_values($rewards),
            'coin'    => $this->getGachaCoinBalance($charId),
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Params: charId, sessionKey, accountId
     * Returns: { status: 1, data: [{id, req, claimed}, ...], total_spins: int }
     */
    public function getBonusGachaRewards($charId, $sessionKey, $accountId)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ChristmasEvent2025.getBonusGachaRewards: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Christmas event is currently inactive.'];
        }

        $charId    = (int) $charId;
        $eventData = CharacterEventData::forCharacter($charId, self::EVENT_KEY_GACHA, 0);
        $extra     = $eventData->extra ?? [];
        $spinCount = $extra['spins'] ?? 0;
        $claimed   = $extra['bonus_claimed'] ?? [];

        $milestones = $config['gacha']['milestone'] ?? [];
        $data = [];
        foreach ($milestones as $i => $entry) {
            $data[] = [
                'id'      => $entry['id'],
                'req'     => $entry['requirement'],
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
     * Params: charId, sessionKey, rewardIndex
     * Returns: { status: 1, reward: string[] }
     */
    public function claimBonusGachaRewards($charId, $sessionKey, $rewardIndex)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ChristmasEvent2025.claimBonusGachaRewards: Char $charId Index $rewardIndex");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Christmas event is currently inactive.'];
        }

        $charId     = (int) $charId;
        $rewardIndex = (int) $rewardIndex;
        $eventData  = CharacterEventData::forCharacter($charId, self::EVENT_KEY_GACHA, 0);
        $extra      = $eventData->extra ?? [];
        $spinCount  = $extra['spins'] ?? 0;
        $claimed    = $extra['bonus_claimed'] ?? [];

        $milestones = $config['gacha']['milestone'] ?? [];
        if (!isset($milestones[$rewardIndex])) {
            return ['status' => 2, 'result' => 'Invalid bonus reward index.'];
        }

        $entry = $milestones[$rewardIndex];
        if ($spinCount < (int) ($entry['requirement'] ?? 0)) {
            return ['status' => 2, 'result' => 'Not enough spins to claim this reward.'];
        }

        if (in_array($rewardIndex, $claimed, true)) {
            return ['status' => 2, 'result' => 'Bonus reward already claimed.'];
        }

        $char = Character::with('user')->find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $rewardStr = $entry['id'];
        (new RewardGrantService())->grant($char, $rewardStr);

        $claimed[]              = $rewardIndex;
        $extra['bonus_claimed'] = $claimed;
        $eventData->extra       = $extra;
        $eventData->save();

        $displayId = str_replace('%s', $char->gender == 0 ? '0' : '1', explode(':', $rewardStr)[0]);

        return [
            'status' => 1,
            'error'  => 0,
            'reward' => [$displayId],
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

        Log::info("AMF ChristmasEvent2025.getPersonalGachaHistory: Char $charId");

        if (!$this->config()) {
            return ['status' => 2, 'result' => 'Christmas event is currently inactive.'];
        }

        $extra     = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY_GACHA, 0)->extra ?? [];
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

        Log::info("AMF ChristmasEvent2025.getGlobalGachaHistory: Char $charId");

        if (!$this->config()) {
            return ['status' => 2, 'result' => 'Christmas event is currently inactive.'];
        }

        return [
            'status'    => 1,
            'error'     => 0,
            'histories' => Cache::get(self::GLOBAL_GACHA_KEY, []),
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function config(): ?array
    {
        $event = GameEvent::where('panel', self::PANEL)->where('active', true)->first();
        if (!$event) return null;
        return $event->data ?? [];
    }

    /**
     * Find a boss config whose id[] array contains the given bossId.
     */
    private function findBoss(array $config, string $bossId): ?array
    {
        foreach ($config['bosses'] ?? [] as $boss) {
            $ids = (array) ($boss['id'] ?? []);
            if (in_array($bossId, $ids, true)) {
                return $boss;
            }
        }
        return null;
    }

    /**
     * Normalise milestone_battle entries to always have a 'requirement' and 'id' key.
     */
    private function milestones(array $config): array
    {
        return array_map(
            fn ($m) => [
                'requirement' => $m['requirement'],
                'id'          => $m['id'] ?? $m['reward'] ?? '',
            ],
            $config['milestone_battle'] ?? $config['milestones'] ?? []
        );
    }

    private function getGachaCoinBalance(int $charId): int
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
            if ($roll <= $cumsum) return $i;
        }
        return count($weights) - 1;
    }

    private function pickFromPool(array $pool): ?string
    {
        if (empty($pool)) return null;
        return $pool[array_rand($pool)];
    }

    private function pushGlobalGachaHistory(array $entry): void
    {
        $history = Cache::get(self::GLOBAL_GACHA_KEY, []);
        array_unshift($history, $entry);
        Cache::put(self::GLOBAL_GACHA_KEY, array_slice($history, 0, 50), 86400 * 7);
    }
}