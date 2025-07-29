<?php

namespace App\Actions\Crawler;

use App\Actions\SendHttpRequestAction;
use App\DTO\ZitaziUpdateDTO;
use App\Models\Currency;
use App\Models\Product;
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
        if ($variation->product->belongsToDecalthon()) {
            app(DecathlonCrawler::class)->crawl($variation);
        } else if ($variation->product->belongsToTrendyol()) {
            app(TrendyolVariationCrawler::class)->crawl($variation);
        };
    }

    protected function getProfitRatioForVariation(Variation $variation): float|int
    {
        if (!empty($variation->product->markup)) {
            return 1 + ($variation->product->markup / 100);
        }

        return 1.6;
    }

    public function syncZitazi(Variation $variation, ZitaziUpdateDTO $dto)
    {
        if ($variation->product?->onPromotion()) {
            return;
        }

        $stockStatus = ZitaziUpdateDTO::OUT_OF_STOCK;
        if ($dto->stock_quantity > 0) {
            $stockStatus = ZitaziUpdateDTO::IN_STOCK;
        }

        $data = $dto->getUpdateBody();

        $data['stock_status'] = $stockStatus;
        $data['sale_price'] = null;
        $data['regular_price'] = '' . $dto->price;


        if ($variation->item_type == Product::PRODUCT_UPDATE) {
            $url = "products/{$variation->product->own_id}";
        } elseif (!empty($variation->own_id)) {
            $url = "products/{$variation->product->own_id}/variations/{$variation->own_id}";
        } else {
            return;
        }

        try {
            $response = $this->woocommerce->post($url, $data);
            Log::info(
                "variation_update_{$variation->id}",
                [
                    'body' => $data,
                    'variation' => $variation->toArray(),
                    'response' => [
                        'price' => data_get($response, 'price'),
                        'sale_price' => data_get($response, 'sale_price'),
                        'regular_price' => data_get($response, 'regular_price'),
                        'stock_quantity' => data_get($response, 'stock_quantity'),
                        'stock_status' => data_get($response, 'stock_status'),
                        'zitazi_id' => data_get($response, 'id'),
                        'variation' => $variation->toArray(),
                    ],
                ]
            );
        } catch (HttpClientException $e) {
            $body = $e->getResponse()->getBody();
            $json = json_decode($body, true);

            Log::error('WooCommerce error variation', [
                'code' => $json['code'] ?? 'unknown',
                'message' => $json['message'] ?? 'No message',
                'variation_id' => $variation->id,
                'json' => $json,
                'body' => $body
            ]);
        } catch (\Exception $e) {
            Log::error('error-sync-variation', [
                'error' => $e->getMessage(),
                'variation_id' => $variation->id,
            ]);
        }

    }

    public function updateVariationAndLog(Variation $variation, $data): void
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

    }
}
