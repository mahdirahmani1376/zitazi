<?php

namespace App\Jobs;

use App\Actions\LogManager;
use App\DTO\ZitaziUpdateDTO;
use App\Enums\SyncStatusEnum;
use App\Models\Product;
use App\Services\WoocommerceService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncWoocommerceProductJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $productId,
        public int $recommendedPrice,
    )
    {
    }

    public function handle(): void
    {
        $data = ZitaziUpdateDTO::getPriceUpdatePayload($this->recommendedPrice);
        $product = Product::find($this->productId);
        $url = "products/{$product->own_id}";

        try {
            $response = WoocommerceService::sendRequest(
                $url,
                $data,
                'post',
                $product->base_source
            );

            $code = $response->json('code');
            $message = $response->json('message');
            if ($response->ok()) {
                LogManager::logProduct($product, 'update successful', [
                    'body' => $data,
                    'response' => $response->body(),
                    'code' => $response->getStatusCode(),
                    'message' => $message
                ]);

                $product->setSyncStatus(SyncStatusEnum::SUCCESSFUL_UPDATE);
            } else {
                LogManager::logProduct($product, 'update failed', [
                    'body' => $data,
                    'response' => $response->body(),
                    'code' => $response->getStatusCode(),
                    'url' => $url,
                    'message' => $message
                ]);

                $product->setSyncStatus(SyncStatusEnum::FAILED_UPDATE);
            }


            if ($code == 'woocommerce_rest_product_invalid_id') {
                $product->setSyncStatus(SyncStatusEnum::INVALID_WOOCOMMERCE_ID);
            }

        } catch (\Exception $e) {
            LogManager::logProduct($product, 'General Exception Occurred', [
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTrace(),
                'product_id' => $product->id,
                'request_body' => $data,
                'request_url' => $url,
            ]);

            $product->setSyncStatus(SyncStatusEnum::FAILED_UPDATE);
        }
    }


}
