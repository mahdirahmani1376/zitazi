<?php

namespace App\Jobs;

use App\Actions\SendHttpRequestAction;
use App\Actions\TrendyolParser;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Variation;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class SeedVariationsForProductJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    private SendHttpRequestAction $sendHttpRequestAction;
    private TrendyolParser $trendyolParser;

    public function __construct(
        private readonly Product $product,
    )
    {
        $this->sendHttpRequestAction = app(SendHttpRequestAction::class);
        $this->trendyolParser = app(TrendyolParser::class);
    }

    public function handle(): void
    {
        $product = $this->product;

        if ($product->belongsToDecalthon()) {
            $this->seedDecathlonVariations($product);
        }
        if ($product->belongsToTrendyol()) {
            $this->seedTrendyolVariations($product);
        }
    }

    private function seedDecathlonVariations(Product $product): void
    {
        $response = Http::withHeaders([
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3',
        ])->get($product->decathlon_url)->body();

        $element = 'script[type="application/ld+json"]';

        $crawler = new Crawler($response);
        $element = $crawler->filter($element)->first();
        $productId = null;

        $variations = [];
        if ($element->count() > 0) {

            $data = collect(json_decode($element->text(), true));
            $productId = data_get($data, 'productID');
            $offers = collect($data->get('offers'))->collapse();
            foreach ($offers as $offer) {
                $stock = $offer['availability'] == 'https://schema.org/InStock' ? 88 : 0;
                $variations[] = [
                    'product_id' => $productId,
                    'sku' => $offer['sku'] ?? null,
                    'price' => $offer['price'] ?? null,
                    'url' => $offer['url'] ?? null,
                    'stock' => $stock,
                ];
            }
        }

        foreach ($variations as $variation) {
            $skuId = $variation['sku'];
            $pattern = '/"skuId"\s*:\s*"' . preg_quote($skuId, '/') . '"\s*,\s*"size"\s*:\s*"([^"]+)"/';

            $jsonString = $crawler->filter('#__dkt')->first();
            if ($jsonString->count() > 0) {
                if (preg_match($pattern, $jsonString->text(), $matches)) {
                    $size = $matches[1];
                    $variation['size'] = $size;
                }
            } else {
                dump("no variation found for {$product->id}");
                Log::info("no variation found for {$product->id}");

                continue;
            }

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

        $colors = $crawler->filter('script[type="application/ld+json"]')->first();
        $json = json_decode($colors->text(), true);

        $colorVariants = data_get($json, 'hasVariant');
        if (!empty($colorVariants)) {
            $this->createMultiVariations($colorVariants, $product);
        } else {
            $this->createSingleVariation($response, $product);
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
        $variantsArray = $this->trendyolParser->parseVariationTypeVariationResponse($response);
        foreach ($variantsArray['data'] as $item) {
            $variation = Variation::updateOrCreate([
                'item_number' => $item['item_number']
            ], [
                'size' => $item['size'],
                'price' => $item['price'],
                'rial_price' => Currency::convertToRial($item['price']) * $product->getRatio(),
                'stock' => $item['stock'],
                'barcode' => $item['barcode'],
                'color' => $variantsArray['color'],
                'url' => $product->trendyol_source,
                'sku' => $variantsArray['sku'],
                'product_id' => $product->id,
                'source' => Product::SOURCE_TRENDYOL,
                'item_type' => $item['item_type']
            ]);
        }
    }

    private function processMultiVariant(mixed $url): array
    {
        $response = $this->sendHttpRequestAction->sendWithCache('get', $url);

        return $this->trendyolParser->parseResponse($response);
    }


}
