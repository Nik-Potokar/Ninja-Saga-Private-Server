<?php

namespace App\Services\Amf\EudemonGardenService;

use App\Models\Character;
use App\Models\GameConfig;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class PurchaseService
{
    use ValidatesSession;

    /**
     * buyTries
     * Params: [sessionKey, charId]
     */
    public function buyTries($sessionKey, $charId)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF EudemonGarden.buyTries: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error_code' => 'Character not found'];

        $user = User::find($char->user_id);
        $cost = ($char->level >= 80) ? 80 : 50;

        if ($user->tokens < $cost) {
            return ['status' => 2]; // Not enough tokens
        }

        $user->tokens -= $cost;
        $user->save();

        $eudemonConfig = GameConfig::get('eudemon', []);
        $bosses = $eudemonConfig['bosses'] ?? [];
        $bossCount = count($bosses);
        $defaultTries = (int) ($eudemonConfig['default_tries'] ?? 3);

        $char->eudemon_garden_tries = implode(',', array_fill(0, $bossCount, $defaultTries));
        $char->save();

        return [
            'status' => 1,
            'data' => $char->eudemon_garden_tries
        ];
    }
}