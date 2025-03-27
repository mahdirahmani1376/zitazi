<?php

namespace App\Jobs;

use App\Actions\SyncVariationsActions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncVariationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $startTime = microtime(true);
        app(SyncVariationsActions::class);
        $endTime = microtime(true);
        $duration = $endTime - $startTime;

    }
}
