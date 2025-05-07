<?php

namespace App\Actions\Crawler;

use App\DTO\ZitaziUpdateDTO;
use App\Exceptions\UnProcessableResponseException;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductCompare;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class TorobCrawler extends BaseCrawler implements ProductAbstractCrawler
{
    public function crawl($product): void
    {
        if (Cache::get(Product::TOROB_LOCK_FOR_UPDATE)) {
            Log::error("skipping-torob-update-{$product->id}");
            return;
        };

        try {
            $agents = [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.4 Safari/605.1.15',
                'Mozilla/5.0 (Linux; Android 11; SM-A505F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Mobile Safari/537.36',
            ];

            $headers = [
                'User-Agent' => $agents[array_rand($agents)],
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Connection' => 'keep-alive',
                'Referer' => 'https://torob.com/', // if you're scraping internal links,
                'Accept-Encoding' => 'gzip, deflate, br',
            ];

            $responseTorob = ($this->sendHttpRequestAction)('get', $product->torob_source, $headers);

            sleep(rand(5, 10));

            if ($responseTorob->status() != 200) {
                $this->LogResponseAndSetLockCache($responseTorob);
                return;
            } else {
                $responseData = $this->parseResponse($responseTorob);
                $this->processDataForProduct($product, $responseData);
            }

        } catch (\Exception $e) {
            Log::error('error_torob_fetch' . $product->id, [
                'error' => $e->getMessage(),
            ]);
        }

    }
    private function parseResponse(Response $responseTorob): Collection
    {
        $responseTorob = $responseTorob->body();
        $crawler = new Crawler($responseTorob);
        $element = $crawler->filter('script#__NEXT_DATA__')->first();
        if ($element->count() > 0) {
            return collect(json_decode($element->text(), true));
        } else {
            throw UnProcessableResponseException::make('torob_parse_error');
        }
    }

    private function processDataForProduct(Product $product, $responseData): void
    {
        $sellers = data_get($responseData, 'props.pageProps.baseProduct.products_info.result');

        if (!empty($sellers) && count($sellers) > 1) {
            $this->compareProductWithOtherSellers($sellers, $product);

        } elseif ($product->isForeign()) {
            $this->updateForeignProduct($product);
        }

    }

    private function compareProductWithOtherSellers(mixed $sellers, Product $product): void
    {
        $zitaziTorobPrice = $this->getZitaziTorobPrice($sellers);
        $torobMinPrice = $this->torobMinPrice($sellers);

        if ($product->belongsToTrendyol()) {
            $this->setMinPriceOfProductForTrendyol($product);
        }

        $zitaziTorobPriceRecommend = $this->getZitaziTorobPriceRecommend($torobMinPrice, $product);

        ProductCompare::updateOrCreate(
            [
                'product_id' => $product->id,
            ],
            [
                'zitazi_torob_price_recommend' => $zitaziTorobPriceRecommend,
                'zitazi_torob_ratio' => !empty($torobMinPrice) ? $zitaziTorobPrice / $torobMinPrice : null,
                'zitazi_torob_price' => $zitaziTorobPrice,
                'torob_min_price' => $torobMinPrice,
            ]
        );

        $this->syncProductWithZitazi($product, ZitaziUpdateDTO::createFromArray([
            'price' => '' . $zitaziTorobPriceRecommend,
        ]));
    }

    private function getZitaziTorobPrice(mixed $sellers): mixed
    {
        return collect($sellers)->firstWhere('shop_id', '=', 12259)['price'] ?? null;
    }

    private function torobMinPrice(mixed $sellers): mixed
    {
        return collect($sellers)->filter(function ($i) {
            return data_get($i, 'shop_id') != 12259;
        })->pluck('price')->filter(fn($p) => $p > 0)->min();
    }

    private function setMinPriceOfProductForTrendyol(Product $product): void
    {
        $minPrice = $product->price * Currency::syncTryRate() * 1.2;
        $product->min_price = floor($minPrice / 10000) * 10000;
        $product->update();
    }

    private function getZitaziTorobPriceRecommend(mixed $torobMinPrice, Product $product): int|float
    {
        $zitazi_torob_price_recommend = $torobMinPrice * (99.5 / 100);

        if (!empty($product->min_price)) {
            if ($zitazi_torob_price_recommend < $product->min_price) {
                $zitazi_torob_price_recommend = $product->min_price;
            }

        }
        $zitazi_torob_price_recommend = floor($zitazi_torob_price_recommend / 10000) * 10000;


        return $zitazi_torob_price_recommend;
    }

    private function updateForeignProduct(Product $product): void
    {
        $stock = 'outofstock';

        if (!empty($product->stock) && $product->stock > 0) {
            $stock = 'instock';
        }

        $updateData = [
            'price' => '' . $product->rial_price,
            'stock_quantity' => $product->stock,
            'stock_status' => $stock,
        ];

        $this->syncProductWithZitazi($product, ZitaziUpdateDTO::createFromArray($updateData));
    }

    public function supports(Product $product): bool
    {
        return $product->belongsToTorob();
    }

    private function LogResponseAndSetLockCache(Response $responseTorob): void
    {
        Log::error('torob-api-failed', [
            'body' => $responseTorob->body(),
        ]);
        Cache::set(Product::TOROB_LOCK_FOR_UPDATE, true, now()->addDay());
    }
}
