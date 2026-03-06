<?php

namespace App\Services\Amf\TalentService;

use App\Models\Character;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class PackageService
{
    use ValidatesSession;

    /**
     * buyPackageTP
     */
    public function buyPackageTP($charIdOrParams, $sessionKey = null, $packageIndex = null)
    {
        if (is_array($charIdOrParams)) {
            $charId = $charIdOrParams[0];
            $sessionKey = $charIdOrParams[1] ?? null;
            $packageIndex = $charIdOrParams[2] ?? null;
        } else {
            $charId = $charIdOrParams;
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF Talent.buyPackageTP: Char $charId Package $packageIndex");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $user = User::find($char->user_id);
        if (!$user) return ['status' => 0, 'error' => 'User not found'];

        $packages = [
            0 => [20, 20],
            1 => [125, 100],
            2 => [250, 200],
            3 => [600, 400]
        ];

        if (!isset($packages[$packageIndex])) {
            return ['status' => 0, 'error' => 'Invalid package'];
        }

        $tpToAdd = $packages[$packageIndex][0];
        $cost = $packages[$packageIndex][1];

        if ($user->tokens < $cost) {
            return ['status' => 2, 'result' => 'Not enough tokens!'];
        }

        $user->tokens -= $cost;
        $user->save();

        $char->tp += $tpToAdd;
        $char->save();

        return [
            'status' => 1,
            'price' => $cost,
            'add' => $tpToAdd
        ];
    }
}
