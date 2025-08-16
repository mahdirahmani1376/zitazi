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

class SyncVariationsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable,SerializesModels;

    public $timeout = 3600;
    public $tries = 2;
    public $backoff = [1 * 60 * 60 * 8];

    public function __construct(
        public Variation $variation
    ) {}

    public function handle(SyncVariationsActions $syncVariationsActions): void
    {
        $syncVariationsActions->execute($this->variation);
    }
}
