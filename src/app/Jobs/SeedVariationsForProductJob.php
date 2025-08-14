<?php

namespace App\Jobs;

use App\Actions\SendHttpRequestAction;
use App\Actions\TrendyolParser;
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
use Symfony\Component\DomCrawler\Crawler;

class SeedVariationsForProductJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    private SendHttpRequestAction $sendHttpRequestAction;
    private TrendyolParser $trendyolParser;

    public function __construct(
        public Product $product,
    )
    {
        $this->sendHttpRequestAction = app(SendHttpRequestAction::class);
        $this->trendyolParser = app(TrendyolParser::class);
    }

    public function handle(): void
    {
        $product = $this->product;

        try {
            if ($product->belongsToDecalthon()) {
                $this->seedDecathlonVariations($product);
            } else if ($product->belongsToTrendyol()) {
                $this->seedTrendyolVariations($product);
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
            $response = app(SendHttpRequestAction::class)->getDecathlonData($product->decathlon_url);
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
        $response = $this->sendHttpRequestAction->sendWithCache('get', $product->trendyol_source);

        $crawler = new Crawler($response);

        try {
            $colors = $crawler->filter('script[type="application/ld+json"]')->first();
            $json = json_decode($colors->text(), true);
            $colorVariants = data_get($json, 'hasVariant');
            if (!empty($colorVariants)) {
                $this->createMultiVariations($colorVariants, $product);
            } else {
                $this->createSingleVariation($response, $product);
            }

        } catch (Exception $e) {
            dump($e->getMessage(),$product->id);
            Log::error('error-seed-variations', [
                'product_id' => $product->id,
                'body' => $colors->text()
            ]);
        }

    }

    private function createMultiVariations(mixed $colorVariants, Product $product): void
    {
        $variantsArray = [];
        foreach ($colorVariants as $colorVariant) {
            $sku = $colorVariant['sku'];
            $url = $colorVariant['offers']['url'];
            $color = $colorVariant['color'];

            $data = $this->processMultiVariant($url);
            $variantsArray[] = [
                'url' => $url,
                'sku' => $sku,
                'color' => $color,
                'data' => $data,
            ];
        }

        foreach ($variantsArray as $variant) {
            $variantSku = $variant['sku'];
            $variantUrl = $variant['url'];
            $variantColor = $variant['color'];

            foreach ($variant['data'] as $item) {
                $variation = Variation::updateOrCreate([
                    'item_number' => $item['item_number']
                ], [
                    'size' => $item['size'],
                    'price' => $item['price'],
                    'rial_price' => Currency::convertToRial($item['price']) * $product->getRatio(),
                    'stock' => $item['stock'],
                    'barcode' => $item['barcode'],
                    'color' => $variantColor,
                    'url' => $variantUrl,
                    'sku' => $variantSku,
                    'product_id' => $product->id,
                    'source' => Product::SOURCE_TRENDYOL,
                    'item_type' => Product::VARIATION_UPDATE
                ]);
            }
        }
    }

    private function createSingleVariation($response, Product $product): void
    {
        [$price,$stock,$sku] = $this->trendyolParser->parseVariationTypeProductResponse($response);
        $variation = Variation::updateOrCreate([
            'product_id' => $product->id,
        ], [
            'price' => $price,
            'rial_price' => Currency::convertToRial($price) * $product->getRatio(),
            'stock' => $stock,
            'url' => $product->trendyol_source,
            'sku' => $sku,
            'source' => Product::SOURCE_TRENDYOL,
            'item_type' => Product::PRODUCT_UPDATE
        ]);
    }

    private function processMultiVariant(mixed $url): array
    {
        $response = $this->sendHttpRequestAction->sendWithCache('get', $url);

        return $this->trendyolParser->parseResponse($response);
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
