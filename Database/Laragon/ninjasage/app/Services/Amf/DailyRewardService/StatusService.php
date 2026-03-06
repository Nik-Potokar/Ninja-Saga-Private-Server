<?php

namespace App\Services\Amf\DailyRewardService;

use App\Models\Character;
use App\Services\Amf\Concerns\ValidatesSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class StatusService
{
    use ValidatesSession;

    /**
     * getDailyData
     * Params: [charId, sessionKey]
     */
    public function getDailyData($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF DailyReward.getDailyData: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $today = Carbon::today();

        // Check if claimed today
        $tokensAvailable = !$char->daily_token_claimed_at || Carbon::parse($char->daily_token_claimed_at)->lt($today);
        $xpAvailable = !$char->daily_xp_claimed_at || Carbon::parse($char->daily_xp_claimed_at)->lt($today);

        // Logic for scroll: "param1.scroll == false" in client means visible (available).
        // Scroll of Wisdom can only be claimed once.
        $scrollClaimed = (bool)$char->daily_scroll_claimed_at;

        $timer = 0;
        if ($char->double_xp_expire_at && Carbon::now()->lt(Carbon::parse($char->double_xp_expire_at))) {
            $timer = Carbon::now()->diffInSeconds(Carbon::parse($char->double_xp_expire_at));
        }

        return [
            'status' => 1,
            'tokens' => $tokensAvailable,
            'xp' => $xpAvailable,
            'scroll' => $scrollClaimed,
            'timer' => $timer
        ];
    }
}
