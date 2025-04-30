<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class UpdateJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

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

        //        Bus::chain([
        //            new SeedJob(),
        //            new SyncProductsJob(),
        //            new SyncVariationsJob(),
        //            new SheerReportJob(),
        //        ])->dispatch();
        Artisan::call('db:seed');
        Artisan::call('app:sync-products');
        Artisan::call('app:sync-variations');
        Artisan::call('app:sheet-report');

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        Log::info('Finished update-job '.Carbon::now()->toDateTimeString().
            '. Duration: '.number_format($duration, 2).' seconds.');

    }
}
