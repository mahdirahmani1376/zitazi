<?php

namespace App\Jobs;

use App\Actions\SyncProductsAction;
use App\Actions\SyncVariationsActions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class UpdateJob implements ShouldQueue
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

        Bus::chain([
            new SeedJob(),
            new SyncProductsJob(),
            new SyncVariationsJob(),
            new SheerReportJob(),
        ])->dispatch();

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        Log::info('Finished update-job '.Carbon::now()->toDateTimeString().
            '. Duration: '.number_format($duration, 2).' seconds.');

    }
}
