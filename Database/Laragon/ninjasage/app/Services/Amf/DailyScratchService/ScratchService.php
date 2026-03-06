<?php

namespace App\Services\Amf\DailyScratchService;

use App\Models\Character;
use App\Models\CharacterPet;
use App\Models\CharacterSkill;
use App\Services\Amf\Concerns\ValidatesSession;
use App\Services\Amf\RewardGrantService;
use Illuminate\Support\Facades\Log;

class ScratchService
{
    use ValidatesSession;

    /**
     * scratch
     * Params: [charId, sessionKey]
     */
    public function scratch($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF DailyScratch.scratch: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        // Recalculate max tickets based on consecutive days
        // Free: 1 ticket. Premium: base 1 + consecutive day bonus
        $isPremium = $char->user && $char->user->account_type == 1;
        $maxTickets = $isPremium ? (1 + (int) $char->daily_scratch_consecutive) : 1;

        if ($char->daily_scratch_count >= $maxTickets) {
            return ['status' => 2, 'result' => 'No tickets left!'];
        }

        // Pick Reward
        $rewardStr = $this->pickReward($char);
        if (!$rewardStr) {
            Log::error('AMF DailyScratch.scratch: No valid reward available', ['char_id' => $char->id]);
            return ['status' => 0, 'error' => 'Invalid scratch reward config'];
        }

        // Grant Reward
        $this->grantReward($char, $rewardStr);
        $char->daily_scratch_count++;
        $char->save();

        // Client expects reward ID/String
        $parts = explode(':', $rewardStr);
        $rewardId = $parts[0];

        // Format special types for client if needed
        if ($rewardId === 'xp_percent') $rewardId = 'xp';
        if ($rewardId === 'tp') $rewardId = 'tp';

        return [
            'status' => 1,
            'reward' => $rewardStr
        ];
    }

    /**
     * getRewards
     */
    private function getRewardsConfig(): array
    {
        $config = \App\Models\GameConfig::get('scratch', []);

        if (is_array($config)) {
            if (isset($config['rewards']) && is_array($config['rewards'])) {
                return $config;
            }
            if (array_is_list($config)) {
                return ['rewards' => $config];
            }
        }

        $legacy = \App\Models\GameConfig::get('scratch_rewards', []);
        return is_array($legacy) ? ['rewards' => $legacy] : [];
    }

    private function pickReward(Character $char): ?string
    {
        $config = $this->getRewardsConfig();
        $rewards = $config['rewards'] ?? [];
        $grandRewards = $config['grand_prize'] ?? [];

        if (!is_array($rewards) || empty($rewards)) {
            return null;
        }

        $eligibleRewards = $this->filterOwnedRewards($char, $rewards);
        $rareRewards = $this->filterOwnedRewards($char, array_filter($rewards, function ($reward) {
            return is_string($reward) && (str_starts_with($reward, 'pet_') || str_starts_with($reward, 'skill_'));
        }));

        $commonRewards = array_values(array_diff($eligibleRewards, $rareRewards));

        $grandProgress = (int) ($char->scratch_grand_progress ?? 0) + 1;
        $rareProgress = (int) ($char->scratch_rare_progress ?? 0) + 1;

        if ($this->shouldGrantGrandPrize($grandProgress, $grandRewards)) {
            $char->scratch_grand_progress = 0;
            $char->scratch_rare_progress = $rareProgress;
            return $this->randomReward($grandRewards);
        }

        if ($this->shouldGrantRarePrize($rareProgress, $rareRewards)) {
            $char->scratch_grand_progress = $grandProgress;
            $char->scratch_rare_progress = 0;
            return $this->randomReward($rareRewards);
        }

        $char->scratch_grand_progress = $grandProgress;
        $char->scratch_rare_progress = $rareProgress;

        if (!empty($commonRewards)) {
            return $this->randomReward($commonRewards);
        }

        if (!empty($eligibleRewards)) {
            return $this->randomReward($eligibleRewards);
        }

        return null;
    }

    private function filterOwnedRewards(Character $char, array $rewards): array
    {
        $filtered = [];

        foreach ($rewards as $reward) {
            if (!is_string($reward)) {
                continue;
            }
            if (str_starts_with($reward, 'pet_')) {
                if (CharacterPet::where('character_id', $char->id)->where('pet_id', $reward)->exists()) {
                    continue;
                }
            }
            if (str_starts_with($reward, 'skill_')) {
                if (CharacterSkill::where('character_id', $char->id)->where('skill_id', $reward)->exists()) {
                    continue;
                }
            }
            $filtered[] = $reward;
        }

        return $filtered;
    }

    private function shouldGrantGrandPrize(int $progress, array $grandRewards): bool
    {
        if (empty($grandRewards)) {
            return false;
        }
        if ($progress < 100) {
            return false;
        }
        if ($progress >= 500) {
            return true;
        }

        $chance = ($progress - 100) / 400; // 0.0 - 1.0 from 100 to 500
        return $this->rollChance($chance);
    }

    private function shouldGrantRarePrize(int $progress, array $rareRewards): bool
    {
        if (empty($rareRewards)) {
            return false;
        }
        if ($progress < 50) {
            return false;
        }
        if ($progress >= 200) {
            return true;
        }

        $chance = ($progress - 50) / 150; // 0.0 - 1.0 from 50 to 200
        return $this->rollChance($chance);
    }

    private function rollChance(float $chance): bool
    {
        $chance = max(0.0, min(1.0, $chance));
        return random_int(1, 10000) <= (int) round($chance * 10000);
    }

    private function randomReward(array $rewards): ?string
    {
        if (empty($rewards)) {
            return null;
        }
        $reward = $rewards[array_rand($rewards)];
        return is_string($reward) ? $reward : null;
    }

    private function grantReward($char, $rewardStr)
    {
        (new RewardGrantService())->grant($char, $rewardStr);
    }
}
