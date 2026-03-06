<?php

namespace App\Console\Commands;

use App\Models\GameEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Automatically activate or deactivate GameEvents based on their
 * starts_at / ends_at schedule.
 *
 * Events without schedule fields are not touched (manual control).
 *
 * Run every minute via the scheduler (routes/console.php).
 */
class SyncGameEventActive extends Command
{
    protected $signature = 'game-events:sync-active
                            {--dry-run : Print what would change without saving}';

    protected $description = 'Activate/deactivate game events based on their starts_at and ends_at schedule.';

    public function handle(): int
    {
        $now      = Carbon::now();
        $dryRun   = $this->option('dry-run');
        $activated = 0;
        $deactivated = 0;

        // Deactivate events whose end time has passed.
        $toDeactivate = GameEvent::query()
            ->where('active', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now)
            ->get();

        foreach ($toDeactivate as $event) {
            $this->line("Deactivate: [{$event->id}] {$event->title} (ends_at={$event->ends_at})");
            if (!$dryRun) {
                $event->update(['active' => false]);
            }
            $deactivated++;
        }

        // Activate events whose start time has arrived (and end time hasn't passed yet).
        $toActivate = GameEvent::query()
            ->where('active', false)
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', $now)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->get();

        foreach ($toActivate as $event) {
            $this->line("Activate:   [{$event->id}] {$event->title} (starts_at={$event->starts_at})");
            if (!$dryRun) {
                $event->update(['active' => true]);
            }
            $activated++;
        }

        if ($activated === 0 && $deactivated === 0) {
            $this->line('No schedule changes needed.');
        } else {
            $this->info("Done. Activated: {$activated}, Deactivated: {$deactivated}" . ($dryRun ? ' [dry-run]' : ''));
        }

        return self::SUCCESS;
    }
}
