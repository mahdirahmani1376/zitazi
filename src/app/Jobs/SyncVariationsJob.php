<?php

namespace App\Jobs;

use App\Actions\SyncVariationsActions;
use App\Models\Variation;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\RateLimiter;

class SyncVariationsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable,SerializesModels;

    public $timeout = 3600;

    public function __construct(
        public Variation $variation
    ) {}

    public function handle(SyncVariationsActions $syncVariationsActions): void
    {
        RateLimiter::attempt(
            'api', // The name we defined earlier
            1, // How many tokens we consume per call
            function () use ($syncVariationsActions) {
                $syncVariationsActions->execute($this->variation);
            },
            $decay = 60 // seconds for the limit window
        );
        sleep(3);

    }
}
