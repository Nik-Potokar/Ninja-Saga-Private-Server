<?php

namespace App\Services\Amf\WelcomeLoginService;

use App\Models\Character;
use App\Services\Amf\Concerns\ValidatesSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class QueryService
{
    use ValidatesSession;

    /**
     * get
     * Parameters: [charId, sessionKey]
     */
    public function get($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF WelcomeLogin.get: Char $charId");

        $char = Character::find($charId);

        // Calculate login day (1-based) based on calendar days in Asia/Kuala_Lumpur
        $tz = 'Asia/Kuala_Lumpur';
        $createdAt = Carbon::parse($char->created_at)->timezone($tz)->startOfDay();
        $now = Carbon::now($tz)->startOfDay();

        // Difference in calendar days + 1
        $loginDays = $createdAt->diffInDays($now) + 1;

        $claimed = [];
        if ($char && $char->claimed_welcome_rewards !== null && $char->claimed_welcome_rewards !== '') {
            $claimed = explode(',', $char->claimed_welcome_rewards);
            $claimed = array_map('trim', $claimed);
        }

        Log::info("WelcomeLogin: Char $charId Day $loginDays Claimed: " . json_encode($claimed));

        $rewards = [];
        $rewardsList = RewardsCatalog::all();
        foreach ($rewardsList as $index => $rewardStr) {
            $isClaimed = in_array((string)$index, $claimed) ? 1 : 0;

            $rewards[] = [
                'r' => $rewardStr,
                'c' => $isClaimed
            ];
        }

        return [
            'status' => 1,
            'error' => 0,
            'logins' => (string)$loginDays,
            'rewards' => $rewards
        ];
    }
}
