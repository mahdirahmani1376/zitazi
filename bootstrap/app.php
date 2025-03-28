<?php

use App\Jobs\SeedJob;
use App\Jobs\SyncProductsJob;
use App\Jobs\SyncVariationsJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
                        $schedule->command('app:sync-variations');
                    }
                );
            }
        );
        $schedule->command('app:sheet-report')->dailyAt('06:00');
        $schedule
            ->command('app:sync-products')->dailyAt('18:30')
            ->after(function () use ($schedule) {
                $schedule->command('app:sync-variations')->dailyAt('19:00');
            });
        $schedule->command('app:index-zitazi-torob-products')->dailyAt('20:00');

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
