<?php

namespace App\Console\Commands;

use App\Models\CrewSeason;
use Illuminate\Console\Command;

class CrewAdvancePhase extends Command
{
    protected $signature = 'crew:advance-phase
                            {--new-season   : Start a brand-new season instead of just advancing the phase}
                            {--phase-hours= : Set how many hours the NEXT phase lasts (sets phase_ends_at)}
                            {--season-days= : When starting a new season, set total season length in days}';

    protected $description = 'Advance the active crew season to the next phase (or start a new season)';

    public function handle(): int
    {
        $season = CrewSeason::where('active', true)->first();

        if (!$season) {
            $this->error('No active crew season found. Run crew:advance-phase --new-season to create one.');
            return 1;
        }

        $phaseHours  = $this->option('phase-hours') ? (int) $this->option('phase-hours') : null;
        $seasonDays  = $this->option('season-days') ? (int) $this->option('season-days') : null;
        $phaseEndsAt = $phaseHours ? now()->addHours($phaseHours) : null;

        if ($this->option('new-season')) {
            $endedAt     = now();
            $endedAt2    = $seasonDays ? now()->addDays($seasonDays) : null;

            $season->update(['active' => false, 'ended_at' => $endedAt]);
            $newNumber = $season->number + 1;

            $new = CrewSeason::create([
                'number'       => $newNumber,
                'active'       => true,
                'phase'        => 1,
                'started_at'   => now(),
                'ended_at'     => $endedAt2,
                'phase_ends_at'=> $phaseEndsAt,
            ]);

            $msg = "Started new crew season #{$new->number} (Phase 1).";
            if ($phaseEndsAt) {
                $msg .= " Phase 1 ends at {$phaseEndsAt->toDateTimeString()}.";
            }
            $this->info($msg);
            return 0;
        }

        $nextPhase = $season->phase >= 2 ? 1 : $season->phase + 1;
        $season->update([
            'phase'         => $nextPhase,
            'phase_ends_at' => $phaseEndsAt,
        ]);

        $msg = "Crew season #{$season->number} advanced to Phase {$nextPhase}.";
        if ($phaseEndsAt) {
            $msg .= " Phase {$nextPhase} ends at {$phaseEndsAt->toDateTimeString()}.";
        }
        $this->info($msg);
        return 0;
    }
}
