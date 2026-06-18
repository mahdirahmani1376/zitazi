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

        return;
//        if ($variation->source == Product::SOURCE_TRENDYOL) {
//            app(TrendyolVariationCrawler::class)->crawl($variation);
//        } else if ($variation->source == Product::SOURCE_DECATHLON) {
//            app(DecathlonCrawler::class)->crawl($variation);
//        } else if ($variation->source == Product::SOURCE_Elele) {
//            app(EleleCrawler::class)->crawl($variation);
//        };
    }

    protected function getProfitRatioForVariation(Variation $variation): float|int
    {
        if (!empty($variation->product->markup)) {
            return 1 + ($variation->product->markup / 100);
        }

        return 1.6;
    }

    public function syncZitazi(Variation $variation, ZitaziUpdateDTO $dto): int
    {
        if (!env('ZITAZ_SYNC_ENABLED', true)) {
            LogManager::logVariation($variation, 'sync is disabled in .env', [
                'variation_id' => $variation->id,
                'data' => $dto->getUpdateBody(),
            ]);
            return 1;
        }

        if ($variation->product->onPromotion()) {
            LogManager::logVariation($variation, 'variation on promotion', [
                'variation_id' => $variation->id,
                'data' => $dto->getUpdateBody(),
            ]);
            return 1;
        }

        if ($variation->is_deleted) {
            LogManager::logVariation($variation, 'variation is deleted', [
                'variation_id' => $variation->id,
                'data' => $dto->getUpdateBody(),
            ]);
            return 1;
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
        if ($variation->item_type == Product::PRODUCT_UPDATE and empty($variation->own_id)) {
            $url = "products/{$variation->product->own_id}";
        } else if ($variation->product->variations()->count() === 1 && empty($variation->own_id)) {

            $url = "products/{$variation->product->own_id}";
        } elseif (!empty($variation->own_id)) {

            $url = "products/{$variation->product->own_id}/variations/{$variation->own_id}";
        } else {
            LogManager::logVariation($variation, 'skipping sync for variation', []);
            return 1;
        }

        try {
            $response = WoocommerceService::sendRequest($url, $data, 'post', $variation->base_source);
            $code = $response->json('code');
            $message = $response->json('message');
            if ($response->ok()) {
                LogManager::logVariation($variation, 'update successful', [
                    'body' => $data,
                    'response' => $response->body(),
                    'code' => $response->getStatusCode()
                ]);
                $variation->update([
                    'status' => Variation::AVAILABLE,
                ]);

                return 0;
            } else {
                LogManager::logVariation($variation, 'update failed', [
                    'body' => $data,
                    'response' => $response->body(),
                    'code' => $response->getStatusCode()
                ]);
            }


            if ($code == 'woocommerce_rest_product_variation_invalid_id') {
                $variation->update([
                    'status' => Variation::INVALID_VARIATION_ID,
                ]);
            }

            if ($code == 'woocommerce_rest_product_invalid_id') {
                $variation->update([
                    'status' => Variation::INVALID_PRODUCT_ID,
                ]);
            }

            if ($response->getStatusCode() === 504) {
                $variation->update([
                    'status' => Variation::STATUS_504,
                ]);
            }

            if ($code === 'unknown' && $message === 'No message' && $response->getStatusCode() !== 504) {
                $variation->update([
                    'status' => Variation::EMPTY_BODY,
                ]);
                return 3;
            }

            return 1;

        } catch (\Exception $e) {

            LogManager::logVariation($variation, 'General Exception Occurred', [
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTrace(),
                'variation_id' => $variation->id,
                'request_body' => $data,
                'request_url' => $url,
            ]);

            $variation->update([
                'status' => Variation::GENERAL_ERROR,
            ]);

            return 1;
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
