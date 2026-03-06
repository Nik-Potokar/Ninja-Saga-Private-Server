<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-activate/deactivate game events based on their starts_at / ends_at schedule.
Schedule::command('game-events:sync-active')->everyMinute()->withoutOverlapping();