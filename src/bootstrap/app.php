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
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('db:seed --force')->dailyAt('12:30')->thenWithOutput(fn($output) => print $output);
        $schedule->command('app:bulk-scrape')->dailyAt('13:00')->thenWithOutput(fn($output) => print $output);
        $schedule->command('model:prune')->dailyAt('00:00')->thenWithOutput(fn($output) => print $output);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
