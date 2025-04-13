<?php

namespace App\Actions\Crawler;

use App\Actions\SendHttpRequestAction;
use App\DTO\ZitaziUpdateDTO;
use App\Models\Currency;
use App\Models\SyncLog;
use App\Models\Variation;
use App\Services\WoocommerceService;
use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;
use Illuminate\Support\Facades\Log;

class BaseVariationCrawler
{
    protected Client $woocommerce;

    protected mixed $rate;

    public function __construct(
        public SendHttpRequestAction $sendHttpRequestAction
    )
    {
        $this->rate = Currency::syncTryRate();
        $this->woocommerce = WoocommerceService::getClient();
    }

    public static function crawlVariation(Variation $variation): void
    {
        app(DecathlonCrawler::class)->crawl($variation);
    }

    protected function syncZitazi(Variation $variation, ZitaziUpdateDTO $dto)
    {
        if ($variation->product?->onPromotion()) {
            return;
        }

        $data = [
            'price' => '' . $dto->price,
            'stock_quantity' => $dto->stock_quantity,
        ];

        $stockStatus = 'outOfStock';
        if ($dto->stock_quantity || $dto->price > 0) {
            $stockStatus = 'inStock';
        }

        $data = $dto->getUpdateBody();

        $data['stock_status'] = $stockStatus;
        $data['sale_price'] = null;
        try {
            $response = $this->woocommerce->post("products/{$variation->product->own_id}/variations/{$variation->own_id}", $data);
            Log::info(
                "product_update_source_{$variation->id}",
                [
                    'price' => data_get($response, 'price'),
                    'sale_price' => data_get($response, 'sale_price'),
                    'regular_price' => data_get($response, 'regular_price'),
                    'stock_quantity' => data_get($response, 'stock_quantity'),
                    'stock_status' => data_get($response, 'stock_status'),
                ]
            );
        } catch (HttpClientException $e) {
            $body = $e->getResponse()->getBody();
            $json = json_decode($body, true);

            Log::error('WooCommerce error variation', [
                'code' => $json['code'] ?? 'unknown',
                'message' => $json['message'] ?? 'No message',
                'variation_id' => $variation->id,
            ]);
        } catch (\Exception $e) {
            Log::error('error-sync-variation', [
                'error' => $e->getMessage(),
                'variation_id' => $variation->id,
            ]);
        }

    }

    protected function updateVariationAndLog(Variation $variation, $data): void
    {
        $oldStock = $variation->stock;
        $oldPrice = $variation->rial_price;

        $variation->update($data);

        if ($oldStock != $variation->stock || $oldPrice != $variation->rial_price) {
            $data = [
                'old_stock' => $oldStock,
                'new_stock' => $variation->stock,
                'old_price' => $oldPrice,
                'new_price' => $variation->rial_price,
                'variation_own_id' => $variation->own_id,
                'product_own_id' => $variation->product->own_id,
            ];

            SyncLog::create($data);
        }

        Log::info("variation_update_{$variation->id}", [
            'before' => $variation->getOriginal(),
            'after' => $variation->getChanges(),
            'data' => $data,
        ]);
    }
}
