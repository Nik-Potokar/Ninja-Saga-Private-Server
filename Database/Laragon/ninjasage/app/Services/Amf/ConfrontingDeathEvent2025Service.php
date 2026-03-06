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
 * ConfrontingDeathEvent2025 AMF Service
 *
 * Energy system: 8 hearts max. Each battle costs 1 heart.
 * Refill: 50 tokens → full 8 hearts.
 * Milestones: 8 milestone slots based on total battles won.
 * Training: purchasable skills (bought with tokens or emblems).
 *
 * Admin GameEvent config (data JSON, panel = "ConfrontingDeathMenu"):
 * {
 *   "energy_max": 8,
 *   "energy_cost": 1,
 *   "refill_token_cost": 50,
 *   "boss_id": "enemy_confronting_1",
 *   "rewards_win": ["material_cd_1:3", "gold_10000"],
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
 *   "skills": [
 *     { "id": "skill_%s_a", "price": [200, 150] },
 *     { "id": "skill_%s_b", "price": [300, 250] }
 *   ]
 * }
 */
class ConfrontingDeathEvent2025Service
{
    use ValidatesSession;

    private const PANEL             = 'ConfrontingDeathMenu';
    private const EVENT_KEY         = 'confronting_death_2025';
    private const ENERGY_MAX        = 8;
    private const ENERGY_COST       = 1;
    private const REFILL_TOKEN_COST = 50;
    private const MILESTONE_COUNT   = 8;

    // -------------------------------------------------------------------------

    public function getBattleData($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ConfrontingDeathEvent2025.getBattleData: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Confronting Death event is currently inactive.'];
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

        Log::info("AMF ConfrontingDeathEvent2025.startBattle: Char $charId Boss $bossId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Confronting Death event is currently inactive.'];
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
            Log::warning("ConfrontingDeath startBattle hash mismatch for Char $charId");
            return ['status' => 2, 'result' => 'Security check failed.'];
        }

        $code = Str::random(16);

        $eventData->energy -= $energyCost;
        $eventData->save();

        Cache::put("cd_battle_{$charId}", [
            'code'   => $code,
            'config' => $config,
        ], 1800);

        return [
            'status' => 1,
            'error'  => 0,
            'code'   => $code,
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

        Log::info("AMF ConfrontingDeathEvent2025.finishBattle: Char $charId Win $win");

        $cached = Cache::get("cd_battle_{$charId}");
        if (!$cached || $cached['code'] !== $battleCode) {
            return ['status' => 2, 'result' => 'Invalid battle session.'];
        }
        Cache::forget("cd_battle_{$charId}");

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

        Log::info("AMF ConfrontingDeathEvent2025.refillEnergy: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Confronting Death event is currently inactive.'];
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

        Log::info("AMF ConfrontingDeathEvent2025.getBonusRewards: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Confronting Death event is currently inactive.'];
        }

        $energyMax  = (int) ($config['energy_max'] ?? self::ENERGY_MAX);
        $eventData  = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, $energyMax);
        $milestones = $this->milestones($config);
        $count      = self::MILESTONE_COUNT;

        // Build rewards array: true = claimed, false = not yet claimed
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

        Log::info("AMF ConfrontingDeathEvent2025.claimBonusRewards: Char $charId Index $rewardIndex");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Confronting Death event is currently inactive.'];
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

    /**
     * Buy a training skill.
     * Params: charId, sessionKey, skillIndex
     *
     * config.skills[skillIndex].price = [non_member_cost, member_cost]
     */
    public function buySkill($charId, $sessionKey, $skillIndex)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ConfrontingDeathEvent2025.buySkill: Char $charId SkillIndex $skillIndex");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Confronting Death event is currently inactive.'];
        }

        $skillIndex = (int) $skillIndex;
        $skills     = $this->skills($config);

        if (!isset($skills[$skillIndex])) {
            return ['status' => 2, 'result' => 'Invalid skill index.'];
        }

        $char = Character::with('user')->find((int) $charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $skill     = $skills[$skillIndex];
        $skillId   = str_replace('%s', $char->gender == 0 ? '0' : '1', (string) $skill['id']);
        $prices    = $skill['price'] ?? [200, 150];
        $user      = $char->user;

        // account_type: 0 = non-member, 1 = member
        // The client hides buy button for index 0 if index 1 is already owned.
        $accountType = $user?->account_type ?? 0;
        $tokenCost   = (int) ($prices[$accountType] ?? $prices[0]);

        if (!$user || $user->tokens < $tokenCost) {
            return ['status' => 2, 'result' => "Not enough tokens (need {$tokenCost})."];
        }

        $granter = new RewardGrantService();
        $granter->grant($char, $skillId);

        $user->tokens -= $tokenCost;
        $user->save();

        return [
            'status' => 1,
            'error'  => 0,
        ];
    }

    // -------------------------------------------------------------------------

    private function config(): ?array
    {
        $event = GameEvent::where('panel', self::PANEL)->where('active', true)->first();
        if (!$event) return null;   // truly inactive / doesn't exist
        return $event->data ?? [];  // event exists; use defaults if data is empty
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

    /**
     * Accept both "skills" (admin JSON) and "training" (seeder).
     */
    private function skills(array $config): array
    {
        return $config['skills'] ?? $config['training'] ?? [];
    }
}