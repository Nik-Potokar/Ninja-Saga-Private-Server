<?php

namespace App\Services\Amf\DailyRewardService;

use App\Models\Character;
use App\Services\Amf\Concerns\ValidatesSession;
use App\Services\Amf\RewardGrantService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    use ValidatesSession;

    /**
     * getAttendances
     * Params: [charId, sessionKey]
     */
    public function getAttendances($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF DailyReward.getAttendances: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $now = Carbon::now();
        $today = $now->day;
        $thisMonth = $now->format('Y-m');

        // Check if reset needed (new month)
        $lastReset = $char->attendance_last_reset ? Carbon::parse($char->attendance_last_reset)->format('Y-m') : null;
        if ($lastReset !== $thisMonth) {
            $char->attendance_days = [];
            $char->attendance_rewards = [0, 0, 0, 0, 0, 0];
            $char->attendance_last_reset = $now->toDateString();
            $char->save();
        }

        // Record today's attendance if not present
        $days = $char->attendance_days ?: [];
        if (!in_array($today, $days)) {
            $days[] = $today;
            sort($days);
            $char->attendance_days = $days;
            $char->save();
        }

        // Define Rewards (Tier 1-6) from Config
        $rewards = \App\Models\GameConfig::get('attendance_rewards', []);

        return [
            'status' => 1,
            'count' => count($days),
            'attendances' => $days,
            'rewards' => $char->attendance_rewards ?: [0, 0, 0, 0, 0, 0],
            'items' => $rewards
        ];
    }

    /**
     * claimAttendanceReward
     * Params: [charId, sessionKey, rewardId]
     */
    public function claimAttendanceReward($charId, $sessionKey, $rewardId)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF DailyReward.claimAttendanceReward: Char $charId Reward $rewardId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $configRewards = \App\Models\GameConfig::get('attendance_rewards', []);
        $reward = null;
        $index = -1;

        foreach ($configRewards as $idx => $r) {
            if ($r['id'] === $rewardId) {
                $reward = $r;
                $index = $idx;
                break;
            }
        }

        if (!$reward) return ['status' => 2, 'result' => 'Invalid Reward'];

        $days = $char->attendance_days ?: [];
        $claimStatuses = $char->attendance_rewards ?: [0, 0, 0, 0, 0, 0];

        if (count($days) < $reward['price']) {
            return ['status' => 2, 'result' => 'Not enough attendance days!'];
        }

        if (isset($claimStatuses[$index]) && $claimStatuses[$index] == 1) {
            return ['status' => 2, 'result' => 'Already claimed!'];
        }

        // Grant Reward
        $levelUp = $this->grantReward($char, $reward['item']);

        // Update Claim Status
        $claimStatuses[$index] = 1;
        $char->attendance_rewards = $claimStatuses;
        $char->save();

        return [
            'status' => 1,
            'reward' => $reward['item'], // Client uses this for visual/HUD update
            'rewards' => $claimStatuses,
            'xp' => $char->xp,
            'level_up' => $levelUp
        ];
    }

    private function grantReward($char, $rewardStr)
    {
        return (new RewardGrantService())->grant($char, $rewardStr);
    }
}
