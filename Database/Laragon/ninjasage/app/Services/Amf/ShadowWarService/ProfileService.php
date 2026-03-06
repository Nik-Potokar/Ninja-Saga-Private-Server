<?php

namespace App\Services\Amf\ShadowWarService;

use App\Models\ShadowWarSeason;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class ProfileService
{
    use ValidatesSession;

    /**
     * getProfile
     * Params: [charId, sessionKey]
     */
    public function getProfile($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = $params[0] ?? null;
        Log::info("AMF ShadowWar.getProfile: Char $charId");

        $seasons = ShadowWarSeason::orderByDesc('num')->get();
        if ($seasons->isEmpty()) {
            $seasons = collect([]);
        }

        $seasonal = [];
        foreach ($seasons as $season) {
            $seasonal[] = [
                'season' => $season->num,
                'started_at' => $season->start_at->format('Y-m-d'),
                'ended_at' => $season->end_at->format('Y-m-d'),
                'stats' => [
                    'attacks' => 0,
                    'attack_win' => 0,
                    'defends' => 0,
                    'defend_win' => 0,
                    'avg_battle_time' => '0:00',
                    'win_rate_attack' => 0,
                    'win_rate_defend' => 0,
                    'total_battles' => 0,
                ],
            ];
        }

        $overall = [
            'seasons_played' => count($seasonal),
            'total_battles' => 0,
            'overall_attack_win_rate' => 0,
            'overall_defend_win_rate' => 0,
            'total_attacks' => 0,
            'total_attack_wins' => 0,
            'total_defends' => 0,
            'total_defend_wins' => 0,
            'avg_battles_per_season' => 0,
            'performance_grade' => 'C',
            'overall_win_rate' => 0,
            'best_season' => 1,
            'best_season_win_rate' => 0,
            'worst_season' => 1,
            'worst_season_win_rate' => 0,
        ];

        return [
            'status' => 1,
            'error' => 0,
            'overall' => $overall,
            'seasonal' => $seasonal,
        ];
    }
}
