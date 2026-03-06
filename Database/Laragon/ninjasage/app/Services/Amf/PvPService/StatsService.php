<?php

namespace App\Services\Amf\PvPService;

use App\Models\Character;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class StatsService
{
    use ValidatesSession;

    public function getCharacterStats($charId, $sessionKey): array
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF PvPService.getCharacterStats: Char $charId");

        $character = Character::find($charId);

        if (!$character) {
            return [
                'status' => 0,
                'error' => 'Character not found',
            ];
        }

        return [
            'status' => 1,
            'data' => [
                'played' => $character->pvp_played,
                'won' => $character->pvp_won,
                'lost' => $character->pvp_lost,
                'pvp_points' => $character->pvp_points,
                'disconnected' => 0,
                'trophy' => $character->pvp_trophy,
                'pvp_version' => 'v0.5.5',
                'pvp_news' => 'Welcome to Ninja Sage PvP!',
                'show_news' => true,
            ],
        ];
    }
}
