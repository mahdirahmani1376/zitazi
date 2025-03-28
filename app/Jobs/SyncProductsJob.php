<?php

namespace App\Jobs;

use App\Actions\SyncProductsAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 3600;

    public function handle(): void
    {
        $startTime = microtime(true);

        SyncProductsAction::dispatch();

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        Log::info('Finished app:sync-products at '.Carbon::now()->toDateTimeString().
            '. Duration: '.number_format($duration, 2).' seconds.');

    }
}
