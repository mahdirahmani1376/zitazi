<?php

namespace App\Jobs;

use App\Actions\LogManager;
use App\Models\Product;
use App\Models\Variation;
use App\Services\WoocommerceService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class BatchSyncZitaziJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Product          $product,
        public array|Collection $variationData,
    )
    {
    }

    public function handle(): void
    {
        $product = $this->product;
        try {
            $url = "/products/{$product->own_id}/variations/batch";
            $woocommerce = WoocommerceService::getClient($product->base_source);
            $response = $woocommerce->post($url, $this->variationData);
            LogManager::logProduct($product, 'update success product', [
                'product_id' => $product->id,
                'response' => json_encode($response)
            ]);
        } catch (\Exception $e) {
            LogManager::logProduct($product, 'batch sync error product', [
                'error' => $e->getMessage(),
                'product_id' => $product->id,
            ]);
            foreach ($product->variations as $variation) {
                $variation->update([
                    'status' => Variation::UNAVAILABLE_ON_ZITAZI,
                ]);
            }
        }
    }
}
