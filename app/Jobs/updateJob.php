<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class updateJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);

        Artisan::call('db:seed');
        Artisan::call('app:sheet-report');
        Artisan::call('app:sync-products');
        Artisan::call('app:sync-variations');

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        Log::info('Finished update-job ' . Carbon::now()->toDateTimeString() .
            '. Duration: ' . number_format($duration, 2) . ' seconds.');

    }
}
