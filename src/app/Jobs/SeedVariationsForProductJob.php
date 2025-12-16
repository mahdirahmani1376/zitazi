<?php

namespace App\Jobs;

use App\Actions\Crawler\EleleCrawler;
use App\Actions\Crawler\SazKalaCrawler;
use App\Actions\HttpService;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Variation;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SeedVariationsForProductJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1 * 60 * 10;
    public $tries = 2;
    public $backoff = [1 * 60 * 5];

    public function __construct(
        public Product $product,
    )
    {
    }

    public function handle(): void
    {
        $product = $this->product;

        try {
            if ($product->belongsToTrendyol()) {
                $this->seedTrendyolVariations($product);
            } else if ($product->belongsToAmazon()) {
                $this->seedAmazonVariations($product);
            } else if ($product->belongsToElele()) {
                $this->seedEleleVariation($product);
            } else {
                Log::error('no url is assigend to product', [
                    'product_id' => $product->id,
                    'product_own_id' => $product->own_id
                ]);
            }
        } catch (Exception $e) {
            Log::error('error-in-seed-variations-for-product', [
                'exception' => $e,
                'trace' => $e->getTrace(),
            ]);
            dump('error-in-seed-variations-for-product', [
                'exception' => $e,
                'trace' => $e->getTrace(),
            ]);
        }

    }

    private function seedTrendyolVariations(Product $product): void
    {
        $response = HttpService::getTrendyolData($product->getTrendyolContentId(), $product->getTrendyolMerchantId());
        $data = collect($response['result']['variants']) ?? [];
        if (empty($data)) {
            Log::error('no variants found for product', [
                'product_id' => $product->id,
                'url' => $product->trendyol_source
            ]);
            return;
        }

        $itemType = count($data) > 1 ? Product::VARIATION_UPDATE : Product::PRODUCT_UPDATE;

        try {
            $availableVariations = [];
            foreach ($data as $item) {
                Variation::updateOrCreate([
                    'item_number' => $item['itemNumber']
                ], [
                    'size' => $item['value'] ?? null,
                    'price' => $price = $item['price']['value'],
                    'rial_price' => Currency::convertToRial($price) * $product->getRatio(),
                    'stock' => !empty($item['inStock']) ? 88 : 0,
                    'barcode' => $item['barcode'],
                    'color' => $item['value'],
                    'url' => Product::PRODUCT_UPDATE ? $product->trendyol_source : data_get($item, 'merchantListing.otherMerchants.0.url', $product->trendyol_source),
                    'sku' => $response['result']['id'] ?? null,
                    'product_id' => $product->id,
                    'source' => Product::SOURCE_TRENDYOL,
                    'item_type' => $itemType,
                    'status' => Variation::AVAILABLE,
                    'item_number' => $item['itemNumber'],
                    'base_source' => $product->base_source,
                ]);

                $availableVariations[] = $item['itemNumber'];

            }

            $unavailableOnSourceSiteVariations = Variation::query()
                ->whereNotIn('item_number', $availableVariations)
                ->where('product_id', $product->id)
                ->where('source', Product::SOURCE_TRENDYOL)
                ->get();

            $unavailableOnSourceSiteVariations->each(fn(Variation $variation) => $variation->update([
                'status' => Variation::UNAVAILABLE_ON_SOURCE_SITE,
            ]));
        } catch (Exception $e) {
            dump($e->getMessage(), $product->id);
            Log::error('error-seed-variations', [
                'product_id' => $product->id,
            ]);
        }

    }

    private function seedAmazonVariations(Product $product): void
    {
        Variation::updateOrCreate([
            'product_id' => $product->id,
        ], [
            'url' => $product->amazon_source,
            'source' => Product::SOURCE_AMAZON,
            'product_id' => $product->id,
            'sku' => $product->amazon_source,
            'item_type' => Product::PRODUCT_UPDATE
        ]);
    }

    private function seedEleleVariation(Product $product)
    {
        $variation = Variation::updateOrCreate([
            'product_id' => $product->id,
        ], [
            'url' => $product->elele_source,
            'source' => Product::SOURCE_Elele,
            'product_id' => $product->id,
            'sku' => $product->elele_source,
            'item_type' => Product::PRODUCT_UPDATE
        ]);

        app(EleleCrawler::class)->crawl($variation);
    }

    private function seedSazkalaVariation(Product $product)
    {
        $variation = Variation::updateOrCreate([
            'product_id' => $product->id,
        ], [
            'url' => $product->sazkala_source,
            'source' => Product::SOURCE_SAZ_KALA,
            'product_id' => $product->id,
            'sku' => null,
            'item_type' => Product::PRODUCT_UPDATE
        ]);

        app(SazKalaCrawler::class)->crawl($variation);
    }


}
