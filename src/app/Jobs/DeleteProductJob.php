<?php

namespace App\Jobs;

use App\DTO\ZitaziUpdateDTO;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeleteProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $productId)
    {
    }

    public function handle(): void
    {
        $product = Product::with('variations')->find($this->productId);

        foreach ($product->variations as $variation) {
            $variation->update([
                'stock' => 0,
            ]);

            SyncZitaziJob::dispatchSync($variation, ZitaziUpdateDTO::createFromArray([
                'stock_quantity' => 0,
            ]));

            Log::info('variation deleted', [
                'variation_id' => $variation->id,
                'woocommerce_id' => $variation->product->own_id,
                'data' => $variation->toArray(),
            ]);

            $variation->delete();
        }

        Log::info('product deleted', [
            'product_id' => $product->id,
            'woocommerce_id' => $product->own_id,
            'data' => $product->toArray(),
        ]);

        $product->delete();
    }
}
