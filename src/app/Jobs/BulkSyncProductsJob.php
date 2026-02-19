<?php

namespace App\Jobs;

use App\Actions\Filament\SyncAndUpdateProductButtonAction;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BulkSyncProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Product $product)
    {
    }

    public function handle(): void
    {
        SyncAndUpdateProductButtonAction::execute($this->product);
    }
}
