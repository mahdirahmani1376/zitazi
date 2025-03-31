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

class SyncProductJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable,SerializesModels;

    public function __construct(
        public Product $product,
    ) {}

    public $timeout = 3600;

    public function handle(): void
    {
        app(SyncProductsAction::class)($this->product);
    }
}
