<?php

namespace App\Services\Amf\DailyRouletteService;

use App\Models\Character;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SpinService
{
    use ValidatesSession;

    /**
     * spin
     */
    public function spin($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF DailyRoulette.spin: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $today = Carbon::today();
        // Ensure date is set (if first spin today)
        if (!$char->daily_roulette_date || Carbon::parse($char->daily_roulette_date)->lt($today)) {
            $char->daily_roulette_date = $today;
            $char->daily_roulette_count = 0;
            $char->save();
        }

        $user = User::find($char->user_id);
        $maxSpins = ($user->account_type == 1) ? 2 : 1;

        if ($char->daily_roulette_count >= $maxSpins) {
            return ['status' => 2]; // Already spun max times
        }

        // Randomize Reward
        $rewards = $this->getRewards();
        $rewardFrame = array_rand($rewards);
        $baseRewardStr = $rewards[$rewardFrame];

        // Apply Consecutive Bonus Multiplier
        $multiplier = $char->daily_roulette_consecutive;
        $finalRewardStr = $this->applyMultiplier($baseRewardStr, $multiplier);

        // Grant Reward
        $levelUp = $this->grantReward($char, $finalRewardStr);

        $char->daily_roulette_count++;
        $char->save();

        return [
            'status' => 1,
            'reward_string' => $finalRewardStr,
            'xp' => $char->xp,
            'tokens' => $user->tokens,
            'gold' => $char->gold,
            'reward' => $rewardFrame,
            'bonus' => $char->daily_roulette_consecutive,
            'level_up' => $levelUp
        ];
    }

    /**
     * getRewards
     */
    private function getRewards()
    {
        return \App\Models\GameConfig::get('roulette_rewards', []);
    }

    private function applyMultiplier($rewardStr, $multiplier)
    {
        $parts = explode('_', $rewardStr);
        $type = $parts[0];
        $val = intval($parts[1]);
        $newVal = $val * $multiplier;
        return $type . '_' . $newVal;
    }

    private function grantReward($char, $rewardStr)
    {
        $parts = explode('_', $rewardStr);
        $type = $parts[0];
        $val = intval($parts[1]);
        $levelUp = false;

        if ($type == 'gold') {
            $char->gold += $val;
        } elseif ($type == 'tokens') {
            $user = User::find($char->user_id);
            $user->tokens += $val;
            $user->save();
        } elseif ($type == 'xp') {
            if ($char->isLevelCapped()) $val = 0;
            $levelUp = $char->addXp($val);
        }
        $char->save();
        return $levelUp;
    }
}
