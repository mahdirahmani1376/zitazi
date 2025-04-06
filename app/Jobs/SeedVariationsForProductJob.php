<?php

namespace App\Jobs;

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
    use Dispatchable, Batchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly Product $product,
        private                  $rate)
    {
    }

    public function handle(): void
    {
        $product = $this->product;

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
                    'stock' => $stock ?? 0,
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
            $rialPrice = $this->rate * $price;
            $rialPrice = $rialPrice * 1.6;

            $rialPrice = floor($rialPrice / 10000) * 10000;

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
}
