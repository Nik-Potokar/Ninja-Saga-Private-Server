<?php

namespace App\Services\Amf\DailyRewardService;

use App\Models\Character;
use App\Services\Amf\Concerns\ValidatesSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class XpService
{
    use ValidatesSession;

    /**
     * claimDailyXP
     * Params: [charId, sessionKey]
     */
    public function claimDailyXP($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF DailyReward.claimDailyXP: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $today = Carbon::today();
        if ($char->daily_xp_claimed_at && Carbon::parse($char->daily_xp_claimed_at)->gte($today)) {
            return ['status' => 2, 'result' => 'Already claimed today!'];
        }

        $config = \App\Models\GameConfig::get('daily_rewards', []);

        $doubleXpChance = $config['double_xp_chance'] ?? 20;
        $roll = mt_rand(1, 100);

        $xpReward = 0;
        $doubleXpDuration = 0;
        $levelUp = false;
        $bonusRate = 0;

        if ($roll <= $doubleXpChance) {
            // Award Bonus XP (Variable Rate 10% - 30%)
            $doubleXpDuration = 3600; // 1 Hour
            $bonusRate = mt_rand(10, 30);

            $char->double_xp_expire_at = Carbon::now()->addSeconds($doubleXpDuration);
            $char->xp_bonus_rate = $bonusRate;
        } else {
            // Award Random XP
            $minMult = $config['xp_min_multiplier'] ?? 50;
            $maxMult = $config['xp_max_multiplier'] ?? 100;
            $multiplier = mt_rand($minMult, $maxMult);

            $xpReward = $char->level * $multiplier;

            if ($char->isLevelCapped()) {
                $xpReward = 0;
            }

            $levelUp = $char->addXp($xpReward);

            // Reset bonus rate if flat XP is awarded (optional, but safer to clear old buff)
            $char->xp_bonus_rate = 0;
            $char->double_xp_expire_at = null;
        }

        $char->daily_xp_claimed_at = now();
        $char->save();

        return [
            'status' => 1,
            'xp' => $char->xp,
            'reward' => $xpReward,
            'level_up' => $levelUp,
            'double_xp' => $doubleXpDuration > 0,
            'timer' => $doubleXpDuration,
            'bonus_rate' => $bonusRate // Send to client if needed
        ];
    }

    /**
     * claimDoubleXP
     * Params: [charId, sessionKey]
     */
    public function claimDoubleXP($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF DailyReward.claimDoubleXP: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $today = Carbon::today();
        if ($char->daily_xp_claimed_at && Carbon::parse($char->daily_xp_claimed_at)->gte($today)) {
            return ['status' => 2, 'result' => 'Already claimed today!'];
        }

        // Set Double XP for 1 Hour
        $duration = 3600; // seconds
        $char->double_xp_expire_at = Carbon::now()->addSeconds($duration);

        // This counts as claiming the XP slot
        $char->daily_xp_claimed_at = now();
        $char->save();

        return [
            'status' => 1,
            'timer' => $duration
        ];
    }
}
