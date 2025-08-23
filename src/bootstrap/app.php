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
        $schedule->command('db:seed')->dailyAt('00:00');
        $schedule->command('app:sync-variations')->dailyAt('01:00');
//        $schedule->command('app:sync-zitazi')->dailyAt('06:00');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
