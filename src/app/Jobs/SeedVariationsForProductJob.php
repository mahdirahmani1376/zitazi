<?php

namespace App\Jobs;

use App\Actions\HttpService;
use App\Exceptions\UnProcessableResponseException;
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

    public $tries = 2;

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
            } elseif ($product->belongsToDecalthon()) {
                $this->seedDecathlonVariations($product);
            } else if ($product->belongsToAmazon()) {
                $this->seedAmazonVariations($product);
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

    private function seedDecathlonVariations(Product $product): void
    {
        try {
            $response = HttpService::getDecathlonData($product->decathlon_url);
        } catch (Exception $exception) {
            Log::error('error-in-seed-variations-for-product', [
                'exception' => $exception,
                'trace' => $exception->getTrace(),
                'product_id' => $product->id,
            ]);
            throw UnProcessableResponseException::make('error-in-seed-variations-for-product');
        }

        foreach ($response['body'] as $variation) {
            $price = (int)str_replace(',', '.', trim($variation['price']));
            $rialPrice = Currency::convertToRial($price) * $product->getRatio();
            if (empty($price) || empty($rialPrice)) {
                $stock = 0;
                $price = null;
                $rialPrice = null;
            }

            $createData = [
                'product_id' => $product->id,
                'sku' => $variation['sku'],
                'price' => $variation['price'],
                'url' => $variation['url'],
                'stock' => $variation['stock'],
                'size' => $variation['size'],
                'rial_price' => $rialPrice,
                'source' => Product::SOURCE_DECATHLON,
                'item_type' => Product::VARIATION_UPDATE
            ];

            $variation = Variation::updateOrCreate([
                'sku' => $variation['sku'],
            ], $createData);

        }

        $defaultVariation = $product->defaultVariation();

        if (!empty($defaultVariation)) {
            $price = $defaultVariation->price;
            $rialPrice = $defaultVariation->rial_price;
            $minPrice = $rialPrice * 1.2;
            $stock = $defaultVariation->stock;

            $product->update([
                'min_price' => $minPrice,
                'rial_price' => $rialPrice,
                'price' => $price,
                'stock' => $stock,
            ]);
        }

    }

    private function seedTrendyolVariations(Product $product): void
    {
        $response = HttpService::getTrendyolData($product->getTrendyolContentId(), $product->getTrendyolMerchantId());
        $data = collect($response['result']['variants']);
        $itemType = count($data) > 1 ? Product::VARIATION_UPDATE : Product::PRODUCT_UPDATE;
        try {
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
                    'sku' => $response['result']['id'],
                    'product_id' => $product->id,
                    'source' => Product::SOURCE_TRENDYOL,
                    'item_type' => $itemType,
                    'status' => Variation::AVAILABLE,
                ]);
            }
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
            'url' => $product->amazon_source,
            'source' => Product::SOURCE_AMAZON,
            'product_id' => $product->id,
            'sku' => $product->amazon_source,
            'item_type' => Product::PRODUCT_UPDATE
        ]);
    }


}
