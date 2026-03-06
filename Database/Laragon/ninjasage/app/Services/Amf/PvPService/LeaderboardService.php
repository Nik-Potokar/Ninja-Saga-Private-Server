<?php

namespace App\Services\Amf\PvPService;

use App\Models\Character;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;
use App\Services\Amf\PvPService\PvpData;

class LeaderboardService
{
    use ValidatesSession;

    public function getLeaderboard($charId, $sessionKey): array
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF PvPService.getLeaderboard: Char $charId");

        $character = Character::find($charId);
        if (!$character) {
            return [
                'status' => 0,
                'error' => 'Character not found',
            ];
        }

        $leaders = Character::orderByDesc('pvp_trophy')
            ->orderBy('id')
            ->limit(100)
            ->get();

        $data = [];
        foreach ($leaders as $leader) {
            $data[] = PvpData::buildLeaderboardEntry($leader);
        }

        $higherTrophy = Character::where('pvp_trophy', '>', $character->pvp_trophy)->count();
        $sameTrophyLowerId = Character::where('pvp_trophy', $character->pvp_trophy)
            ->where('id', '<', $character->id)
            ->count();

        return [
            'status' => 1,
            'data' => $data,
            'trophy' => $character->pvp_trophy ?? 0,
            'pos' => $higherTrophy + $sameTrophyLowerId + 1,
        ];
    }
}
