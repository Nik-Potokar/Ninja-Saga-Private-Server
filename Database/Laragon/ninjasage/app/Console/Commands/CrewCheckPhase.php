<?php

namespace App\Console\Commands;

use App\Models\CrewSeason;
use Illuminate\Console\Command;

class CrewCheckPhase extends Command
{
    protected $signature = 'crew:check-phase';

    protected $description = 'Automatically advance the crew phase if phase_ends_at has passed';

    public function handle(): int
    {
        $season = CrewSeason::where('active', true)->first();

        if (!$season) {
            $this->info('No active crew season found.');
            return 0;
        }

        if (!$season->phase_ends_at) {
            $this->info("Season #{$season->number} has no automatic phase end set.");
            return 0;
        }

        if (now()->lt($season->phase_ends_at)) {
            $remaining = now()->diffForHumans($season->phase_ends_at, true);
            $this->info("Phase {$season->phase} ends in {$remaining}.");
            return 0;
        }

        $nextPhase = $season->phase >= 2 ? 1 : $season->phase + 1;
        $season->update([
            'phase'         => $nextPhase,
            'phase_ends_at' => null,  // clear so it doesn't keep cycling
        ]);

        $this->info("Auto-advanced season #{$season->number} to Phase {$nextPhase}.");
        return 0;
    }
}
