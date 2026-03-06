<?php

namespace App\Services\Amf;

use App\Models\Character;
use App\Models\CharacterEventData;
use App\Models\GameEvent;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * HalloweenEvent2025 AMF Service
 *
 * Energy system: 8 hearts max. Each battle costs 1 heart.
 * Refill: 50 tokens → full 8 hearts.
 * Multiple selectable bosses, each with its own reward pool.
 * 8 milestones based on total battles won.
 *
 * Admin GameEvent config (data JSON, panel = "HalloweenMenu"):
 * {
 *   "energy_max": 8,
 *   "energy_cost": 1,
 *   "refill_token_cost": 50,
 *   "bosses": [
 *     {
 *       "id": ["enemy_hw_1"],
 *       "name": "Boss Name",
 *       "description": "...",
 *       "levels": [-5, 5],
 *       "gold": "level*100",
 *       "rewards": ["material_hw_1:3", "gold_10000"],
 *       "background": "field_bg"
 *     }
 *   ],
 *   "milestone_battle": [
 *     { "id": "item_%s_hair_1", "requirement": 5,   "quantity": 1 },
 *     { "id": "item_%s_set_1",  "requirement": 10,  "quantity": 1 },
 *     { "id": "gold_50000",     "requirement": 20,  "quantity": 1 },
 *     { "id": "tp_200",         "requirement": 30,  "quantity": 1 },
 *     { "id": "item_%s_back_1", "requirement": 50,  "quantity": 1 },
 *     { "id": "gold_100000",    "requirement": 70,  "quantity": 1 },
 *     { "id": "skill_%s_xxx",   "requirement": 90,  "quantity": 1 },
 *     { "id": "skill_%s_yyy",   "requirement": 120, "quantity": 1 }
 *   ],
 *   "rewards_preview": {
 *     "hair": [], "set": [], "back": [], "weapon": [], "skill": []
 *   }
 * }
 */
class HalloweenEvent2025Service
{
    use ValidatesSession;

    private const PANEL             = 'HalloweenMenu';
    private const EVENT_KEY         = 'halloween_2025';
    private const ENERGY_MAX        = 8;
    private const ENERGY_COST       = 1;
    private const REFILL_TOKEN_COST = 50;
    private const MILESTONE_COUNT   = 8;

    // -------------------------------------------------------------------------

    public function getBattleData($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF HalloweenEvent2025.getBattleData: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Halloween event is currently inactive.'];
        }

        $energyMax = (int) ($config['energy_max'] ?? self::ENERGY_MAX);
        $eventData = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, $energyMax);

        return [
            'status' => 1,
            'error'  => 0,
            'energy' => $eventData->energy,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Battle.as params: charId, bossId, agility, enemyStats, hash, sessionKey
     *
     * bossId is one of the enemy IDs from bosses[i].id[] array.
     * The server resolves which boss config to use via findBoss().
     */
    public function startBattle($charId, $bossId, $agility, $enemyStats, $hash, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF HalloweenEvent2025.startBattle: Char $charId Boss $bossId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Halloween event is currently inactive.'];
        }

        $energyMax  = (int) ($config['energy_max'] ?? self::ENERGY_MAX);
        $energyCost = (int) ($config['energy_cost'] ?? self::ENERGY_COST);
        $eventData  = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, $energyMax);

        if ($eventData->energy < $energyCost) {
            return ['status' => 2, 'result' => 'Not enough energy. Please refill to continue.'];
        }

        $boss = $this->findBoss($config, $bossId);
        if (!$boss) {
            Log::warning("HalloweenEvent2025 startBattle: unknown bossId '$bossId' for Char $charId");
            return ['status' => 2, 'result' => 'Unknown boss.'];
        }

        // Validate client hash: sha256(charId + bossId + enemyStats + agility)
        $expectedHash = hash('sha256', $charId . $bossId . $enemyStats . $agility);
        if (!hash_equals($expectedHash, (string) $hash)) {
            Log::warning("HalloweenEvent2025 startBattle hash mismatch for Char $charId");
            return ['status' => 2, 'result' => 'Security check failed.'];
        }

        $code = Str::random(16);

        $eventData->energy -= $energyCost;
        $eventData->save();

        Cache::put("hw_battle_{$charId}", [
            'code'    => $code,
            'boss_id' => $bossId,
            'boss'    => $boss,
            'config'  => $config,
        ], 1800);

        // Server hash client verifies: sha256(bossId + code + charId)
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
     * Battle.as params: charId, bossId, battleCode, totalDamage, hash, win, sessionKey
     *
     * Rewards come from the specific boss config (boss.rewards[]), not a global list.
     */
    public function finishBattle($charId, $bossId, $battleCode, $totalDamage, $hash, $win, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF HalloweenEvent2025.finishBattle: Char $charId Win $win");

        $cached = Cache::get("hw_battle_{$charId}");
        if (!$cached || $cached['code'] !== $battleCode) {
            return ['status' => 2, 'result' => 'Invalid battle session.'];
        }
        Cache::forget("hw_battle_{$charId}");

        $char = Character::with('user')->find((int) $charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $config  = $cached['config'];
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

            $energyMax = (int) ($config['energy_max'] ?? self::ENERGY_MAX);
            CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, $energyMax)->increment('battles');
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

        Log::info("AMF HalloweenEvent2025.refillEnergy: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Halloween event is currently inactive.'];
        }

        $refillCost = (int) ($config['refill_token_cost'] ?? self::REFILL_TOKEN_COST);
        $energyMax  = (int) ($config['energy_max'] ?? self::ENERGY_MAX);

        $char = Character::with('user')->find((int) $charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $user = $char->user;
        if (!$user || $user->tokens < $refillCost) {
            return ['status' => 2, 'result' => "Not enough tokens (need {$refillCost})."];
        }

        $user->tokens -= $refillCost;
        $user->save();

        $eventData         = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, $energyMax);
        $eventData->energy = $energyMax;
        $eventData->save();

        return [
            'status' => 1,
            'error'  => 0,
            'energy' => $energyMax,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * HalloweenMenu.as calls this with an extra trailing `0` argument (page).
     * The $page param is accepted but not used (no pagination needed).
     */
    public function getBonusRewards($charId, $sessionKey, $page = 0)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF HalloweenEvent2025.getBonusRewards: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Halloween event is currently inactive.'];
        }

        $energyMax = (int) ($config['energy_max'] ?? self::ENERGY_MAX);
        $eventData = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, $energyMax);
        $count     = self::MILESTONE_COUNT;

        $rewardsState = [];
        for ($i = 0; $i < $count; $i++) {
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

        Log::info("AMF HalloweenEvent2025.claimBonusRewards: Char $charId Index $rewardIndex");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Halloween event is currently inactive.'];
        }

        $rewardIndex = (int) $rewardIndex;
        $milestones  = $this->milestones($config);

        if (!isset($milestones[$rewardIndex])) {
            return ['status' => 2, 'result' => 'Invalid milestone index.'];
        }

        $energyMax = (int) ($config['energy_max'] ?? self::ENERGY_MAX);
        $eventData = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, $energyMax);
        $milestone = $milestones[$rewardIndex];

        if ($eventData->battles < (int) ($milestone['requirement'] ?? 0)) {
            return ['status' => 2, 'result' => 'Milestone requirement not met yet.'];
        }

        if ($eventData->hasMilestoneClaimed($rewardIndex)) {
            return ['status' => 2, 'result' => 'Milestone already claimed.'];
        }

        $char = Character::with('user')->find((int) $charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $rewardId = $milestone['reward'] ?? $milestone['id'] ?? '';
        $granter  = new RewardGrantService();
        $granter->grant($char, (string) $rewardId);
        $eventData->claimMilestone($rewardIndex);

        return [
            'status' => 1,
            'error'  => 0,
            'reward' => [$rewardId],
        ];
    }

    // -------------------------------------------------------------------------

    private function config(): ?array
    {
        $event = GameEvent::where('panel', self::PANEL)->where('active', true)->first();
        if (!$event) return null;
        return $event->data ?? [];
    }

    /**
     * Find a boss config by matching bossId against each boss's id[] array.
     * HalloweenMenu bosses have an array of enemy IDs in their "id" field.
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
     * Accept both the admin-JSON format (milestone_battle with {id, requirement, quantity})
     * and the legacy format ({requirement, reward}).
     */
    private function milestones(array $config): array
    {
        if (!empty($config['milestones'])) {
            return $config['milestones'];
        }
        return array_map(
            fn ($m) => ['requirement' => $m['requirement'], 'reward' => $m['id'] ?? '', 'id' => $m['id'] ?? ''],
            $config['milestone_battle'] ?? []
        );
    }
}
