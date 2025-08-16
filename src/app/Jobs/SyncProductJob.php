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

    public $timeout = 3600;
    public $tries = 2;
    public $backoff = [1 * 60 * 60 * 8];
    public function __construct(
        public Product $product,
    ) {}

    public function handle(): void
    {
        if ($this->batch()->cancelled()) {
            return;
        }
        try {
            app(SyncProductsAction::class)->execute($this->product);
        } catch (\Exception $e) {
            \Log::error('SyncProductJob exception', [
                'exception' => $e,
                'trace' => $e->getTrace(),
            ]);
        }
    }
}
