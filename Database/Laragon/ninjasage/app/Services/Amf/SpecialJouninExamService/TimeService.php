<?php

namespace App\Services\Amf\SpecialJouninExamService;

use App\Models\Character;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class TimeService
{
    use ValidatesSession;

    /**
     * extraTime
     */
    public function extraTime($sessionKey, $charId)
    {
        // Handle swapped parameters [charId, sessionKey]
        if (is_numeric($sessionKey) && !is_numeric($charId)) {
            $temp = $sessionKey;
            $sessionKey = $charId;
            $charId = $temp;
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF SpecialJouninExam.extraTime: Char $charId");

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        $user = User::find($char->user_id);
        if ($user->tokens < 30) {
            return ['status' => 2, 'result' => 'Not enough tokens!'];
        }

        $user->tokens -= 30;
        $user->save();

        return [
            'status' => 1,
            'result' => 'ok'
        ];
    }
}
