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
 * ThanksGivingEvent2025 AMF Service  (in-client name: "Feast of Gratitude")
 *
 * Energy system: 10 hearts max. Each battle costs 1 heart.
 * Refill: 50 tokens → full 10 hearts.
 * 4 bosses selectable by the player.
 * 8 milestones based on total battles won.
 * Package: one-time purchasable set of rewards.
 *
 * Admin GameEvent config (data JSON, panel = "FeastOfGratitudeMenu"):
 * {
 *   "energy_max": 10,
 *   "energy_cost": 1,
 *   "refill_token_cost": 50,
 *   "rewards_win": ["material_tg_1:3", "gold_10000"],
 *   "milestones": [
 *     { "requirement": 5,  "reward": "item_%s_hair_1" },
 *     { "requirement": 10, "reward": "item_%s_set_1"  },
 *     { "requirement": 20, "reward": "gold_50000"     },
 *     { "requirement": 30, "reward": "tp_200"         },
 *     { "requirement": 50, "reward": "item_%s_back_1" },
 *     { "requirement": 70, "reward": "gold_100000"    },
 *     { "requirement": 90, "reward": "skill_%s_xxx"   },
 *     { "requirement": 120,"reward": "skill_%s_yyy"   }
 *   ],
 *   "package": {
 *     "price": [200, 150],
 *     "rewards": ["skill_%s_a", "item_bg_1", "gold_100000", "material_tg_1:10"]
 *   }
 * }
 */
class ThanksGivingEvent2025Service
{
    use ValidatesSession;

    private const PANEL             = 'FeastOfGratitudeMenu';
    private const EVENT_KEY         = 'thanksgiving_2025';
    private const ENERGY_MAX        = 10;
    private const ENERGY_COST       = 1;
    private const REFILL_TOKEN_COST = 50;
    private const MILESTONE_COUNT   = 8;

    // -------------------------------------------------------------------------

    public function getBattleData($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ThanksGivingEvent2025.getBattleData: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Feast of Gratitude event is currently inactive.'];
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
     */
    public function startBattle($charId, $bossId, $agility, $enemyStats, $hash, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ThanksGivingEvent2025.startBattle: Char $charId Boss $bossId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Feast of Gratitude event is currently inactive.'];
        }

        $energyMax  = (int) ($config['energy_max'] ?? self::ENERGY_MAX);
        $energyCost = (int) ($config['energy_cost'] ?? self::ENERGY_COST);
        $eventData  = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, $energyMax);

        if ($eventData->energy < $energyCost) {
            return ['status' => 2, 'result' => 'Not enough energy. Please refill to continue.'];
        }

        // Validate client hash: sha256(charId + bossId + enemyStats + agility)
        $expectedHash = hash('sha256', $charId . $bossId . $enemyStats . $agility);
        if (!hash_equals($expectedHash, (string) $hash)) {
            Log::warning("ThanksGiving startBattle hash mismatch for Char $charId");
            return ['status' => 2, 'result' => 'Security check failed.'];
        }

        $code = Str::random(16);

        $eventData->energy -= $energyCost;
        $eventData->save();

        Cache::put("tg_battle_{$charId}", [
            'code'    => $code,
            'boss_id' => $bossId,
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
     */
    public function finishBattle($charId, $bossId, $battleCode, $totalDamage, $hash, $win, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ThanksGivingEvent2025.finishBattle: Char $charId Win $win");

        $cached = Cache::get("tg_battle_{$charId}");
        if (!$cached || $cached['code'] !== $battleCode) {
            return ['status' => 2, 'result' => 'Invalid battle session.'];
        }
        Cache::forget("tg_battle_{$charId}");

        $char = Character::with('user')->find((int) $charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $config  = $cached['config'];
        $rewards = $config['rewards_win'] ?? [];
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

        Log::info("AMF ThanksGivingEvent2025.refillEnergy: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Feast of Gratitude event is currently inactive.'];
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

    public function getBonusRewards($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ThanksGivingEvent2025.getBonusRewards: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Feast of Gratitude event is currently inactive.'];
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

        Log::info("AMF ThanksGivingEvent2025.claimBonusRewards: Char $charId Index $rewardIndex");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Feast of Gratitude event is currently inactive.'];
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

        $char    = Character::with('user')->find((int) $charId);
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

    public function getPackage($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ThanksGivingEvent2025.getPackage: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Feast of Gratitude event is currently inactive.'];
        }

        $energyMax = (int) ($config['energy_max'] ?? self::ENERGY_MAX);
        $eventData = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, $energyMax);

        return [
            'status' => 1,
            'error'  => 0,
            'bought' => $eventData->bought,
        ];
    }

    // -------------------------------------------------------------------------

    public function buyPackage($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ThanksGivingEvent2025.buyPackage: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Feast of Gratitude event is currently inactive.'];
        }

        $energyMax = (int) ($config['energy_max'] ?? self::ENERGY_MAX);
        $eventData = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, $energyMax);

        if ($eventData->bought) {
            return ['status' => 2, 'result' => 'Package already purchased.'];
        }

        $package   = $config['package'] ?? [];
        $prices    = $package['price'] ?? [200, 150];
        $char      = Character::with('user')->find((int) $charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $user        = $char->user;
        $accountType = $user?->account_type ?? 0;
        $tokenCost   = (int) ($prices[$accountType] ?? $prices[0]);

        if (!$user || $user->tokens < $tokenCost) {
            return ['status' => 2, 'result' => "Not enough tokens (need {$tokenCost})."];
        }

        $user->tokens -= $tokenCost;
        $user->save();

        $granter  = new RewardGrantService();
        $rewards  = $package['rewards'] ?? [];
        foreach ($rewards as $rewardStr) {
            $granter->grant($char, (string) $rewardStr);
        }

        $eventData->bought = true;
        $eventData->save();

        return [
            'status' => 1,
            'error'  => 0,
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
     * Accept both the admin-JSON format ({requirement, reward})
     * and the seeder format ({requirement, id, quantity}).
     */
    private function milestones(array $config): array
    {
        if (!empty($config['milestones'])) {
            return $config['milestones'];
        }
        return array_map(
            fn ($m) => ['requirement' => $m['requirement'], 'reward' => $m['id'] ?? ''],
            $config['milestone_battle'] ?? []
        );
    }
}