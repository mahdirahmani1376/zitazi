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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncVariationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels,Batchable;

    public $timeout = 3600;

    public function __construct(
        public Variation $variation
    )
    {
    }

    public function handle(SyncVariationsActions $syncVariationsActions): void
    {
        $syncVariationsActions($this->variation);
    }
}
