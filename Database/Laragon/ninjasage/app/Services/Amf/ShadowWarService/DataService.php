<?php

namespace App\Services\Amf\ShadowWarService;

use App\Models\Character;
use App\Models\ShadowWarSeason;
use App\Models\ShadowWarSquad;
use App\Services\Amf\Concerns\ValidatesSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DataService
{
    use ValidatesSession;

    /**
     * getSeason
     * Params: [charId, sessionKey]
     */
    public function getSeason($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = $params[0] ?? null;
        Log::info("AMF ShadowWar.getSeason: Char $charId");

        $activeSeason = ShadowWarSeason::where('active', true)->orderByDesc('num')->first();
        if (!$activeSeason) {
            $activeSeason = ShadowWarSeason::orderByDesc('num')->first();
        }

        return [
            'status' => 1,
            'error' => 0,
            'active' => $activeSeason ? (bool)$activeSeason->active : false,
            'season' => [
                'num' => $activeSeason ? $activeSeason->num : 0,
                'date' => $activeSeason
                    ? Carbon::parse($activeSeason->start_at)->format('d/m/Y') . ' - ' . Carbon::parse($activeSeason->end_at)->format('d/m/Y')
                    : '',
                'time' => $activeSeason
                    ? max(0, (int)Carbon::now()->diffInSeconds(Carbon::parse($activeSeason->end_at), false))
                    : 0,
            ],
        ];
    }

    /**
     * getStatus
     * Params: [charId, sessionKey]
     */
    public function getStatus($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = $params[0] ?? null;
        Log::info("AMF ShadowWar.getStatus: Char $charId");

        $char = $charId ? Character::find($charId) : null;
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        if ($char->rank < Character::RANK_SPECIAL_JOUNIN && $char->level < 60) {
            return [
                'status' => 2,
                'result' => 'Your character must pass the special jounin exam or higher or equal to level 60'
            ];
        }

        $activeSeason = ShadowWarSeason::where('active', true)->orderByDesc('num')->first();
        if (!$activeSeason) {
            return [
                'status' => 2,
                'result' => 'No active season'
            ];
        }

        $assignment = ShadowWarSquad::where('character_id', $char->id)
            ->where('season_id', $activeSeason->id)
            ->first();

        if (!$assignment) {
            $assignment = ShadowWarSquad::create([
                'character_id' => $char->id,
                'season_id' => $activeSeason->id,
                'squad' => $this->pickBalancedSquad($activeSeason->id),
                'rank' => 0,
                'trophy' => 0,
                'energy' => 100,
                'energy_last_reset' => Carbon::now()->toDateString(),
                'energy_refills_today' => 0
            ]);
        }

        $today = Carbon::now()->toDateString();
        if ($assignment->energy_last_reset !== $today) {
            $assignment->energy = 100;
            $assignment->energy_last_reset = $today;
            $assignment->energy_refills_today = 0;
            $assignment->save();
        }

        $squad = (int)$assignment->squad;
        $showProfile = $assignment->wasRecentlyCreated ?? false;

        $squads = [];
        for ($i = 0; $i < 5; $i++) {
            $sum = ShadowWarSquad::where('season_id', $activeSeason->id)
                ->where('squad', $i)
                ->sum('trophy');
            $squads[] = [
                'squad' => $i,
                'trophy' => $sum,
            ];
        }

        return [
            'status' => 1,
            'error' => 0,
            'show_profile' => $showProfile,
            'squad' => $squad,
            'trophy' => (int)$assignment->trophy,
            'rank' => (int)$assignment->rank,
            'energy' => (int)$assignment->energy,
            'squads' => $squads,
        ];
    }

    private function pickBalancedSquad(int $seasonId): int
    {
        $totals = [];
        $counts = [];
        $levelTotals = [];
        $rankTotals = [];
        for ($i = 0; $i < 5; $i++) {
            $totals[$i] = 0;
            $counts[$i] = 0;
            $levelTotals[$i] = 0;
            $rankTotals[$i] = 0;
        }

        $assignments = ShadowWarSquad::where('season_id', $seasonId)->get(['character_id', 'squad', 'trophy', 'rank']);
        foreach ($assignments as $assignment) {
            $squad = (int)$assignment->squad;
            $totals[$squad] += (int)$assignment->trophy;
            $rankTotals[$squad] += (int)$assignment->rank;
            $counts[$squad] += 1;
        }

        if ($assignments->isNotEmpty()) {
            $characterIds = $assignments->pluck('character_id')->unique()->all();
            $levels = Character::whereIn('id', $characterIds)->pluck('level', 'id');
            foreach ($assignments as $assignment) {
                $squad = (int)$assignment->squad;
                $levelTotals[$squad] += (int)($levels[$assignment->character_id] ?? 0);
            }
        }

        $scores = [];
        for ($i = 0; $i < 5; $i++) {
            $count = max(1, $counts[$i]);
            $avgLevel = $levelTotals[$i] / $count;
            $avgRank = $rankTotals[$i] / $count;
            $scores[$i] = $totals[$i]
                + ($avgLevel * 25)
                + ($avgRank * 15)
                + ($counts[$i] * 50);
        }

        $minScore = min($scores);
        $candidates = [];
        foreach ($scores as $squad => $score) {
            if ($score === $minScore) {
                $candidates[] = $squad;
            }
        }

        return $candidates[array_rand($candidates)];
    }
}
