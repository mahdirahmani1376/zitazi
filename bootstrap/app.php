<?php

use App\Jobs\SyncVariationJob;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('db:seed')->dailyAt('05:00')->after(
            function () use ($schedule) {
                $schedule->command('app:sync-products')->after(
                    function () use ($schedule) {
                        $schedule->job(SyncVariationJob::class);
                    }
                );
            }
        );
        $schedule->command('app:sheet-report')->dailyAt('06:00');
        $schedule->command('app:sync-products')->dailyAt('18:30');
        $schedule->job(SyncVariationJob::class)->dailyAt('19:00');
        $schedule->command('app:index-zitazi-torob-products')->dailyAt('20:00');

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
