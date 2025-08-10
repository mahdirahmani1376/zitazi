<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        api: __DIR__ . '/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('db:seed')->dailyAt('01:00');
        $schedule->command('app:sync-products')->dailyAt('03:00');
        $schedule->command('app:sync-variations')->dailyAt('05:00');
//        $schedule->command('app:sheet-report')->dailyAt('08:00');
        //        $schedule->command('app:index-zitazi-torob-products')->dailyAt('08:30');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
