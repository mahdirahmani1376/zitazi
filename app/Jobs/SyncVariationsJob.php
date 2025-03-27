<?php

namespace App\Jobs;

use App\Actions\SyncVariationsActions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncVariationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $startTime = microtime(true);
        app(SyncVariationsActions::class);
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        Log::info('Finished app:sync-variations at ' . Carbon::now()->toDateTimeString() .
            '. Duration: ' . number_format($duration, 2) . ' seconds.');

    }
}
