<?php

namespace App\Services\Amf\DailyRewardService;

use App\Models\Character;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TokenService
{
    use ValidatesSession;

    /**
     * getDailyTokenData
     * Params: [charId, sessionKey]
     */
    public function getDailyTokenData($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF DailyReward.getDailyTokenData: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $today = Carbon::today();
        if ($char->daily_token_claimed_at && Carbon::parse($char->daily_token_claimed_at)->gte($today)) {
            return ['status' => 2, 'result' => 'Already claimed today!'];
        }

        $config = \App\Models\GameConfig::get('daily_rewards', []);
        $tokenAmount = $config['token_amount'] ?? 25;

        $user = User::find($char->user_id);
        $user->tokens += $tokenAmount;
        $user->save();

        $char->daily_token_claimed_at = now();
        $char->save();

        return ['status' => 1];
    }
}
