<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'amf',
            'gateway.php',
            'clan/*',
            'crew/*',
        ]);

        $middleware->alias([
            'clan.auth' => \App\Http\Middleware\ClanAuthMiddleware::class,
            'crew.auth' => \App\Http\Middleware\CrewAuthMiddleware::class,
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // Auto-advance crew phase when phase_ends_at has passed
        $schedule->command('crew:check-phase')->everyFifteenMinutes();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();