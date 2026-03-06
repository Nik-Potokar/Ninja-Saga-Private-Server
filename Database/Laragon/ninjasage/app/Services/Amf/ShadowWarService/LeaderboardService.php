<?php

namespace App\Services\Amf\ShadowWarService;

use App\Models\Character;
use App\Models\ShadowWarSeason;
use App\Models\ShadowWarSquad;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class LeaderboardService
{
    use ValidatesSession;

    /**
     * globalLeaderboard
     * Params: [charId, sessionKey]
     */
    public function globalLeaderboard($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = $params[0] ?? null;
        Log::info("AMF ShadowWar.globalLeaderboard: Char $charId");

        [$players, $squadTotals] = $this->buildPlayers();
        $squads = $this->buildSquads($squadTotals);

        return [
            'status' => 1,
            'error' => 0,
            'players' => $players,
            'squads' => $squads,
        ];
    }

    /**
     * squadLeaderboard
     * Params: [charId, sessionKey, squadId]
     */
    public function squadLeaderboard($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = $params[0] ?? null;
        $squadId = (int)($params[2] ?? 0);
        Log::info("AMF ShadowWar.squadLeaderboard: Char $charId Squad $squadId");

        [$players] = $this->buildPlayers($squadId);

        return [
            'status' => 1,
            'error' => 0,
            'players' => $players,
        ];
    }

    private function buildPlayers(?int $squadFilter = null): array
    {
        $activeSeason = ShadowWarSeason::where('active', true)->orderByDesc('num')->first();
        if (!$activeSeason) {
            return [[], [0, 0, 0, 0, 0]];
        }
        $query = Character::query()->orderByDesc('level');
        $characters = $query->limit(100)->get();

        $players = [];
        $squadTotals = [0, 0, 0, 0, 0];
        foreach ($characters as $char) {
            if ($char->rank < Character::RANK_SPECIAL_JOUNIN && $char->level < 60) {
                continue;
            }
            $assignment = ShadowWarSquad::where('season_id', $activeSeason->id)
                ->where('character_id', $char->id)
                ->first();
            if (!$assignment) {
                continue;
            }
            $squad = (int)$assignment->squad;
            $trophy = (int)$assignment->trophy;
            $rank = (int)$assignment->rank;
            if ($squadFilter !== null && $squadFilter !== $squad) {
                continue;
            }
            $score = $trophy;
            $squadTotals[$squad] += $score;
            $players[] = [
                'id' => $char->id,
                'name' => $char->name,
                'level' => $char->level,
                'trophy' => $score,
                'rank' => $rank,
                'squad' => $squad,
            ];
        }

        usort($players, function ($a, $b) {
            return $b['trophy'] <=> $a['trophy'];
        });

        return [$players, $squadTotals];
    }

    private function buildSquads(array $squadTotals): array
    {
        $squads = [];

        for ($i = 0; $i < 5; $i++) {
            $trophy = $squadTotals[$i] ?? 0;
            $squads[] = ['squad' => $i, 'trophy' => $trophy];
        }

        return $squads;
    }

    // Intentionally no bonus scoring; leaderboard uses real battle trophies only.
}
