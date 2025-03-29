<?php

namespace App\Jobs;

use App\Actions\SyncProductsAction;
use App\Models\Product;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels,Batchable;

    public function __construct(
        public Product $product,
    )
    {
    }

    public $timeout = 3600;

    public function handle(): void
    {
        app(SyncProductsAction::class)($this->product);
    }
}
