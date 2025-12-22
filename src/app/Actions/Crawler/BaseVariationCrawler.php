<?php

namespace App\Actions\Crawler;

use App\Actions\HttpService;
use App\Actions\LogManager;
use App\DTO\ZitaziUpdateDTO;
use App\Models\Currency;
use App\Models\Product;
use App\Models\SyncLog;
use App\Models\Variation;
use App\Services\WoocommerceService;
use Automattic\WooCommerce\HttpClient\HttpClientException;

class BaseVariationCrawler
{
    protected mixed $rate;

    public function __construct(
        public HttpService $httpService
    )
    {
        $this->rate = Currency::syncTryRate();
    }

    public static function crawlVariation(Variation $variation): void
    {
        if (empty($variation->source)) {
            LogManager::logVariation($variation, 'no source variation', [
                'variation_id' => $variation->id,
                'own_id' => $variation->own_id,
            ]);
            return;
        };
        if ($variation->source == Product::SOURCE_TRENDYOL) {
            app(TrendyolVariationCrawler::class)->crawl($variation);
        } else if ($variation->source == Product::SOURCE_DECATHLON) {
            app(DecathlonCrawler::class)->crawl($variation);
        } else if ($variation->source == Product::SOURCE_Elele) {
            app(EleleCrawler::class)->crawl($variation);
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
        if (!env('ZITAZ_SYNC_ENABLED', true)) {
            LogManager::logVariation($variation, 'skipping sync for variation', [
                'variation_id' => $variation->id,
                'data' => $dto->getUpdateBody(),
            ]);
            return;
        }

        if ($variation->product->onPromotion() or $variation->is_deleted) {
            LogManager::logVariation($variation, 'skipping sync for variation', [
                'variation_id' => $variation->id,
                'data' => $dto->getUpdateBody(),
            ]);
            return;
        }

        $stockStatus = ZitaziUpdateDTO::OUT_OF_STOCK;
        if ($dto->stock_quantity > 0) {
            $stockStatus = ZitaziUpdateDTO::IN_STOCK;
        }

        $data = $dto->getUpdateBody();

        $data['stock_status'] = $stockStatus;

        if (!empty($dto?->price)) {
            $data['sale_price'] = null;
            $data['regular_price'] = '' . $dto->price;
        }

//        $data['manage_stock'] = false;
//        if ($variation->product?->brand === 'lego') {
//            $data['manage_stock'] = true;
//        }

        if ($variation->item_type == Product::PRODUCT_UPDATE and empty($variation->own_id)) {
            $url = "products/{$variation->product->own_id}";
        } elseif (!empty($variation->own_id)) {
            $url = "products/{$variation->product->own_id}/variations/{$variation->own_id}";
        } else {
            Log::error('skipping sync for variation', [
                'variation_id' => $variation->id,
            ]);
        }

        try {
            $woocommerce = WoocommerceService::getClient($variation->base_source);
            $response = $woocommerce->post($url, $data);
            LogManager::logVariation($variation, 'variation_update', [
                'body' => $data,
                'response' => [
                    'price' => data_get($response, 'price'),
                    'sale_price' => data_get($response, 'sale_price'),
                    'regular_price' => data_get($response, 'regular_price'),
                    'stock_quantity' => data_get($response, 'stock_quantity'),
                    'stock_status' => data_get($response, 'stock_status'),
                    'zitazi_id' => data_get($response, 'id'),
                ],
            ]);
        } catch (HttpClientException $e) {
            $body = $e->getResponse()->getBody();
            $json = json_decode($body, true);

            LogManager::logVariation($variation, 'WooCommerce error variation', [
                'code' => $json['code'] ?? 'unknown',
                'message' => $json['message'] ?? 'No message',
                'variation_id' => $variation->id,
                'json' => $json,
                'body' => $body
            ]);

            $variation->update([
                'status' => Variation::UNAVAILABLE_ON_ZITAZI,
            ]);
        } catch (\Exception $e) {
            LogManager::logVariation($variation, 'error-sync-variation', [
                'error' => $e->getMessage(),
                'variation_id' => $variation->id,
            ]);
            $variation->update([
                'status' => Variation::UNAVAILABLE_ON_ZITAZI,
            ]);
        }

    }
    public function updateVariationAndLog(Variation $variation, $data): void
    {
        $oldStock = $variation->stock;
        $oldPrice = $variation->rial_price;
        $data['updated_at'] = now();

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
