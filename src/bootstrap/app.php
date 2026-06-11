<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('db:seed --force')->dailyAt('09:00');
        $schedule->command('app:bulk-scrape')->dailyAt('15:00');
        $schedule->command('app:sync-zitazi')->dailyAt('21:00');
        $schedule->command('app:sync-satreh')->dailyAt('21:00');
        $schedule->command('model:prune')
            ->dailyAt('00:00');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
