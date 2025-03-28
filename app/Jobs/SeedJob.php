<?php

namespace App\Jobs;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct() {}

    public function handle(): void
    {
        $startTime = microtime(true);

        app(DatabaseSeeder::class)->run();

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        Log::info('Finished seed-job '.Carbon::now()->toDateTimeString().
            '. Duration: '.number_format($duration, 2).' seconds.');

    }
}
