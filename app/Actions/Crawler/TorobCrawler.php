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
        try {
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:137.0) Gecko/20100101 Firefox/137.0',
//                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
//                'Accept-Language' => 'en-US,en;q=0.5',
//                'Accept-Encoding' => 'gzip, deflate, br, zstd',
//                'Upgrade-Insecure-Requests' => '1',
//                'Sec-Fetch-Dest' => 'document',
//                'Sec-Fetch-Mode' => 'navigate',
//                'Sec-Fetch-Site' => 'none',
//                'Sec-Fetch-User' => '?1',
//                'Connection' => 'keep-alive',
//                'Cookie' => 'returning_user=true; _ga_RWKMFFVXJX=GS1.1.1745659793.26.1.1745659826.0.0.0; _ga=GA1.1.1811146057.1742477026; search_session=eaqkxqxrtxvbjfkedvseopqxycjkfijj; _ga_DG18N985FG=GS1.1.1744457392.1.1.1744457452.0.0.0; _ga_S1W5P3WLLJ=GS1.1.1744457415.1.1.1744457477.0.0.0; csrftoken=Umzus7ARY9anfSp4e0QHSebSNLtmXZhy; trb_clearance=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDU2NjE1OTAsIm5iZiI6MTc0NTY1OTc5MCwic3ViIjoiMzc2MzFiMjhlMjRkNzg2NTJiYjNiZGEyNmJiZTFjYTAyNmJhNzU0ZDZkNDhkNWEyMjc1ZDliOThiMDQwYTU1ZiJ9.axhd_azU0Rf8uvvFiVrUQdrlF0azDEsUPtIgl_YXRes; display_mode=; is_torob_user_logged_in=True; user_access_dict="eyJ1c2VyX3R5cGUiOiAic2hvcF9zdGFmZiIsICJpbnN0YW5jZXMiOiB7fX0="',
//                'Priority' => 'u=0, i',
//                'Pragma' => 'no-cache',
//                'Cache-Control' => 'no-cache',
//                'TE' => 'trailers'
            ];

            $responseTorob = ($this->sendHttpRequestAction)('get', $product->torob_source, $headers);

            sleep(rand(5, 8));

            if ($responseTorob->status() != 200) {
                throw UnProcessableResponseException::make('torob-ban');
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
}
