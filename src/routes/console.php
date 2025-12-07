<?php

use App\Actions\HttpService;
use App\Actions\SyncVariationsActions;
use App\DTO\ZitaziUpdateDTO;
use App\Jobs\SeedVariationsForProductJob;
use App\Jobs\SyncVariationsJob;
use App\Jobs\SyncZitaziJob;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Variation;
use App\Services\WoocommerceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

Artisan::command('test', function () {

    $headers = [
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3',
    ];
    $responseTorob = Http::withHeaders($headers)->acceptJson()
        ->get('https://torob.com/p/fed4cdaf-1292-4efc-9105-ad7a4cb7c8ab/%D8%AA%D9%84%D8%B3%DA%A9%D9%88%D9%BE-%D9%86%D8%AC%D9%88%D9%85%DB%8C-%D9%88-%D8%B7%D8%A8%DB%8C%D8%B9%D8%AA%DA%AF%D8%B1%D8%AF%DB%8C-%D8%B2%DB%8C%D8%AA%D8%A7%D8%B2%DB%8C-master-70-%D8%A8%D8%A7-%D8%A8%D8%B2%D8%B1%DA%AF%D9%86%D9%85%D8%A7%DB%8C%DB%8C-210-%D8%A8%D8%B1%D8%A7%D8%A8%D8%B1-%D8%A7%D8%B1%D8%B3%D8%A7%D9%84-%D9%81%D9%88%D8%B1%DB%8C/')
        ->body();

    $torobPrice = null;
    $crawler = new Crawler($responseTorob);
    $element = $crawler->filter('script#__NEXT_DATA__')->first();
    if ($element->count() > 0) {
        $data = collect(json_decode($element->text(), true));
        $torobPrice = data_get($data, 'props.pageProps.baseProduct.price');
    }

    dd($torobPrice);
});
Artisan::command('test-torob', function () {
    $headers = [
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3',
    ];
    $responseTorob = Http::withHeaders($headers)->acceptJson()
//        ->get('https://torob.com/p/fed4cdaf-1292-4efc-9105-ad7a4cb7c8ab/%D8%AA%D9%84%D8%B3%DA%A9%D9%88%D9%BE-%D9%86%D8%AC%D9%88%D9%85%DB%8C-%D9%88-%D8%B7%D8%A8%DB%8C%D8%B9%D8%AA%DA%AF%D8%B1%D8%AF%DB%8C-%D8%B2%DB%8C%D8%AA%D8%A7%D8%B2%DB%8C-master-70-%D8%A8%D8%A7-%D8%A8%D8%B2%D8%B1%DA%AF%D9%86%D9%85%D8%A7%DB%8C%DB%8C-210-%D8%A8%D8%B1%D8%A7%D8%A8%D8%B1-%D8%A7%D8%B1%D8%B3%D8%A7%D9%84-%D9%81%D9%88%D8%B1%DB%8C/')
        ->get('https://torob.com/p/ea1b1064-c96b-49b4-b6c7-ec3d2aa67f62/لگو-دوپلو-مدل-کلاسیک-کد-10914/')
        ->body();

    $torobPrice = null;
    $crawler = new Crawler($responseTorob);
    $element = $crawler->filter('script#__NEXT_DATA__')->first();
    if ($element->count() > 0) {
        $data = collect(json_decode($element->text(), true));
        $sellers = data_get($data, 'props.pageProps.baseProduct.products_info.result');
        $zitaziPrice = collect($sellers)->firstWhere('shop_id', '=', 12259)['price'] ?? null;
        $minPrice = collect($sellers)->pluck('price')->filter(fn($p) => $p > 0)->min();
        dd($zitaziPrice, $minPrice);
    }
});
Artisan::command('test-excel', function () {
});
Artisan::command('test-digi', function () {
    //    $response = \Illuminate\Support\Facades\Http::get('https://api.digikala.com/v2/product/18087380/')->collect();
    // $response = \Illuminate\Support\Facades\Http::get('https://api.digikala.com/v2/product/14851833/')->collect();
    $response = Http::get('https://api.digikala.com/v2/product/16723546/')->collect();

    $variants = collect(data_get($response, 'data.product.variants'))
        ->map(function ($item) {
            $item['seller_id'] = data_get($item, 'seller.id');

            return $item;
        })->keyBy('seller_id');
    $zitaziPrice = data_get($variants, '69.price.selling_price');
    $minPrice = $variants->pluck('price.selling_price')->min();
    dd($zitaziPrice, $minPrice);
});
Artisan::command('test-update', function () {
    dump(1);
    Log::info('test-update');
    sleep(5);
});
Artisan::command('test-products', function () {
    $response = WoocommerceService::getClient()->get('products');

    dd(collect($response)->pluck('id'));
});
Artisan::command('testd', function () {
    $product = Product::whereNotNull('decathlon_url')->first();

    $response = Http::acceptJson()
        // ->get('https://www.decathlon.com.tr/p/erkek-su-gecirmez-outdoor-kar-montu-kislik-mont-kahverengi-sh500-10-degc/_/R-p-331992?mc=8641932')
        ->get($product->url)
        ->body();

    $element = 'script[type="application/ld+json"]';

    $crawler = new Crawler($response);
    $element = $crawler->filter($element)->first();
    $jsonString = $crawler->filter('#__dkt')->first()->text();

    $variations = [];
    if ($element->count() > 0) {
        $data = collect(json_decode($element->text(), true));
        $productId = data_get($data, 'productID');
        $offers = collect($data->get('offers'))->collapse();
        foreach ($offers as $offer) {
            $stock = $offer['availability'] == 'https://schema.org/InStock' ? 1 : 0;
            $variations[] = [
                'product_id' => $productId,
                'sku' => $offer['sku'] ?? null,
                'price' => $offer['price'] ?? null,
                'url' => $offer['url'] ?? null,
                'stock' => $stock,
            ];
        }
    }

    // $pattern = '/^__DKT\s*=\s*(.*);$/';
    // $pattern = '/^__DKT\s*=\s*(\{.*\})$/';
    foreach ($variations as &$variation) {
        $skuId = $variation['sku'];
        $pattern = '/"skuId"\s*:\s*"' . preg_quote($skuId, '/') . '"\s*,\s*"size"\s*:\s*"([^"]+)"/';

        if (preg_match($pattern, $jsonString, $matches)) {
            $size = $matches[1];
            $variation['size'] = $size;
        }
    }

    foreach ($variations as $variation) {
        Variation::create([
            // 'product_id' => $variation['product_id'],
            'product_id' => $product->id,
            'sku' => $variation['sku'],
            'price' => $variation['price'],
            'url' => $variation['url'],
            'stock' => $variation['stock'],
            'size' => $variation['size'],
        ]);
    }

});
Artisan::command('torob_add_id', function () {
    foreach (Product::whereNotNull('torob_source')->get() as $product) {

        $product->update([
            'torob_id' => !empty($product->torob_source) ? data_get(explode('/', urldecode($product->torob_source)), 4) : null,
        ]);
    }
});
Artisan::command('elele_test', function () {
    //    $url = 'https://www.elelebaby.com/elele-misto-4in1-bebek-mama-sandalyesi-ve-mama-oturagi-ic-pedli-yesil';
    $url = 'https://www.elelebaby.com/elele-lula-travel-sistem-bebek-arabasi-siyah-gri';

    $response = Http::withHeaders(
        [
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3',
        ]
    )->get($url)->body();

    $crawler = new Crawler($response);

    foreach (range(2, 5) as $i) {
        $dom = $crawler->filter("#formGlobal > script:nth-child($i)")->first();

        if ($dom->count() > 0) {
            preg_match('/"productPriceKDVIncluded":([0-9]+\.[0-9]+)/', $dom->text(), $matches);

            if (isset($matches[1])) {
                $price = $matches[1];
                dump($price);
                $price = $price * 1.60 * Currency::syncTryRate();
                $price = (int)($price) * 10000 / 10000;
                dump($price);
                break;

            }

            dump($i);

        }

        $stockElement = 'input.Addtobasket.button.btnAddBasketOnDetail';
        $stockResult = $crawler->filter($stockElement)->first();
        $stock = 0;
        if ($stockResult->count() > 0) {
            $stock = 88;
        }

        dump($stock);
    }
});
Artisan::command('test', function () {
    dump(Product::first());
});
Artisan::command('digi-cat', function () {
    $url = 'https://api.digikala.com/v1/categories/stroller-and-carrier/search/?has_selling_stock=1&q=کالسکه&sort=7';

    $response = Http::get($url)->json();

    $products = data_get($response, 'data.products');

    $categories = [];
    foreach ($products as $product) {
        $categories[$product['id']]['base_category'] = data_get($product, 'data_layer.category');
        foreach (range(2, 7) as $i) {
            $text = "item_category{$i}";
            if (!empty($category = data_get($product, "data_layer.{$text}"))) {
                $categories[$product['id']]['sub_category'][] = $category;
            }
        }
    }

    dump($categories);

});
Artisan::command('subcat-group', function () {
    $q = \App\Models\SubCategory::query()
        ->selectRaw('name,count(name)')
        ->groupBy('name')
        ->get()
        ->toArray();

    dump($q);
});
Artisan::command('ebek-test', function () {

    $url = 'https://www.e-bebek.com/joie-i-juva-step-travel-sistem-bebek-arabasi-p-joi-t1608dasha001';

    $response = Http::withHeaders(
        [
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3',
        ]
    )->get($url)->body();

    $crawler = new Crawler($response);

    //    foreach (range(41, 45) as $i) {
    //        $dom = $crawler->filter("head > script:nth-child({$i})")->first();
    $dom = $crawler->filter('script[type="application/ld+json"]')->eq(0);
    //        dump($i);

    if ($dom
        //            &&
        //            preg_match('/^\{"@co/', $dom->text(), $matches)
    ) {
        $data = json_decode($dom->text(), true);
        $price = $data['offers']['price'];
        dump($price);
        $price = $price * 1.60 * Currency::syncTryRate();
        $price = (int)($price) * 10000 / 10000;
        dump($price);

        $stock = $data['offers']['availability'] == 'https://schema.org/InStock' ? 88 : 0;

        dump($stock);

        //            break;

        //        }

    }

});
Artisan::command('toyz_shop', function () {
    $url = 'http://www.toyzzshop.com/monster-high-gizemli-sirlar-havali-pijama-partisi-serisi-surpriz-paket-hyv64?serial=92603';
    $response = Http::withHeaders(
        [
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3',
        ]
    )->get($url)->body();

    $crawler = new Crawler($response);

    $dom = $crawler->filter('script[type="application/ld+json"]')->eq(1);
    if ($dom->count() > 0) {
        $data = json_decode($dom->text(), true);
        $price = $data['offers']['lowPrice'];
        $price = $price * 1.60 * Currency::syncTryRate();
        $price = (int)($price) * 10000 / 10000;
    }

    preg_match('/"stock"\s*:\s*(\d+)/', $response, $matches);

    if (!empty($matches[1])) {
        $stock = intval($matches[1]);
    }

    dump($price, $stock);

});
//Artisan::command('sync-variations', function (\App\Actions\SyncVariationsActions $syncVariationsActions) {
//    $syncVariationsActions->execute();
//});

Artisan::command('test-job', function () {
    \App\Jobs\TestJob::dispatch();
});

Artisan::command('cancel-batch {batch}', function ($batch) {
    \Illuminate\Container\Container::getInstance()->make(\Illuminate\Bus\BatchRepository::class)?->find($batch)?->cancel();
});

Artisan::command('test', function () {
    \App\Jobs\FailedJob::dispatch();
});

Artisan::command('sync-torob', function () {
    $products = Product::where(
        'torob_source', '!=', '')
        ->get()->map(function (Product $product) {
            return new \App\Jobs\SyncProductJob($product);
        });

//    Artisan::call('db:seed --class=ProductSeeder');

    \Illuminate\Support\Facades\Cache::forget(Product::TOROB_LOCK_FOR_UPDATE);

    \Illuminate\Support\Facades\Bus::batch($products)->name('sync-torob')->dispatch();
});

Artisan::command('test', function () {
    \Illuminate\Support\Facades\Cache::set('test', 'test');
    \Illuminate\Support\Facades\Schema::getTables();
});

Artisan::command('test-matilda', function () {
    $response = app(\App\Actions\HttpService::class)('get', 'https://matiilda.com/product/figure-joytoy-world-war-ii-65748/');

    $crawler = new Crawler($response);

    $dom = $crawler->filter('script[type="application/ld+json"]')->first();

    if ($dom->count() > 0) {
        $data = json_decode($dom->text(), true);
        $price = data_get($data, '@graph.4.offers.price', 0) / 10;
        $stock = data_get($data, '@graph.4.offers.availability', 'https://schema.org/OutOfStock');
    }

});

Artisan::command('test-trendyol-variation', function () {
    $response = Http::get('https://www.trendyol.com/lightinghm/65-lt-konfor-serisi-ortopedik-askeri-taktik-dagci-kamp-trekking-seyahat-sirt-cantasi-siyah-p-34926487');

    $crawler = new Crawler($response);

    $dom = $crawler->filter('script[type="application/ld+json"]')->first();

    $variants = [];

    if ($dom->count() > 0) {
        $data = json_decode($dom->text(), true);
        if ($variantsData = data_get($data, 'hasVariant')) {
            foreach ($variantsData as $variantData) {

                $variants[] = [
                    'sku' => data_get($variantData, 'sku'),
                    'color' => data_get($variantData, 'color'),
                    'price' => data_get($variantData, 'offers.price'),
                    'stock' => data_get($data, 'offers.availability'),
                ];
            }
        }


        $variants[] = [
            'sku' => data_get($data, 'sku'),
            'color' => data_get($data, 'color'),
            'price' => data_get($data, 'offers.price'),
            'stock' => data_get($data, 'offers.availability'),
        ];

        foreach ($variants as &$variant) {
            $variant['price'] = Currency::convertToRial($variant['price']);
            $variant['stock'] = $variant['stock'] == 'https://schema.org/InStock' ? 88 : 0;
        }

        dd($variants);

    }
});

Artisan::command('test-torob-cache', function () {
    Cache::forget(Product::TOROB_LOCK_FOR_UPDATE);
    $product = Product::query()->whereNot('torob_id', '=', '')->first();
    Cache::forget($product->torob_id);
    app(\App\Actions\Crawler\TorobCrawler::class)->crawl($product);
    dump($product->getChanges());
});

Artisan::command('test-digikala-cache', function () {
    $product = Product::query()->whereNot('digikala_source', '=', '')->first();
    app(\App\Actions\Crawler\DigikalaCrawler::class)->crawl($product);
    dump($product->getChanges());
});

Artisan::command('test-product-sync', function () {
    $product = Product::query()
        ->whereNot('digikala_source', '=', '')
        ->whereNot('trendyol_source', '=', '')
        ->where('decathlon_url', '=', '')
        ->whereNot('digikala_source', '=', '')
        ->first();

    dump($product->toArray());
    app(\App\Actions\Crawler\CrawlerManager::class)->crawl($product);
    dump($product->getChanges());
});

Artisan::command('test-trendyol-sync', function () {
//    $variation = \App\Models\Product::firstWhere('own_id',3658);
    app(\App\Actions\Crawler\TrendyolVariationCrawler::crawlVariation($variation = Variation::find(3658)));
    dump($variation->toArray());
});

Artisan::command('test-trendyol-sync-variations {variation}', function ($variation) {
    app(\App\Actions\Crawler\TrendyolVariationCrawler::crawlVariation(Variation::findOrFail($variation)));
});

Artisan::command('ttbw {id}', function ($id) {
    $product = Product::firstWhere('own_id', $id);
    $var = $product->variations()->first();

    dump($product->trendyol_source);
    dump($var->url);

    app(\App\Actions\Crawler\TrendyolVariationCrawler::crawlVariation($var));

});

Artisan::command('test-amazon', function (\App\Actions\HttpService $httpService) {
    $url = 'https://www.amazon.ae/dp/B09NLFPD4Q';

    dd(str_split($url, '/'));
    $response = $httpService->sendAmazonRequest($url);
    $c = new Crawler($response);

    $priceData = $c->filter('div.twister-plus-buying-options-price-data')->first();
    $priceData = json_decode($priceData->text(), true);
    $price = (int)$priceData['desktop_buybox_group_1'][0]['priceAmount'];
    $stockElement = $c->filter('div#outOfStock');

    if ($stockElement->count()) {
        $stock = 0;
    } else $stock = 88;

    dd($price, $stock);
});

Artisan::command('elle-crawl {id}', function ($id) {
    app(\App\Actions\Crawler\EleleCrawler::class)->crawl(Product::findOrFail($id));
});

Artisan::command('test-try', function () {
    \Illuminate\Support\Facades\Cache::delete('try_rate');
    dump(Currency::syncTryRate());
});

Artisan::command('sync-all-trendyol', function () {
    $jobs = Variation::query()
        ->where(function (Builder $query) {
            $query
                ->where(function (Builder $query) {
                    $query->whereNot('url', '=', '')
                        ->where(function (Builder $query) {
                            $query
                                ->whereNotNull('own_id')
                                ->orWhere('item_type', '=', Product::PRODUCT_UPDATE);
                        });
                })
                ->whereRelation('product', 'trendyol_source', '!=', '');
        })
        ->get()
        ->map(function (Variation $variation) {
            return new SyncVariationsJob($variation);
        });

    Bus::batch($jobs)
        ->then(fn() => Log::info('All variations updated successfully.'))
        ->catch(fn() => Log::error('Some jobs failed.'))
        ->name('Import Trendyol')
        ->dispatch();
});

//'https://www.trendyol.com/lego/star-wars-501-klon-trooperlar-paketi-75345-6-yaratici-oyuncak-yapim-seti-119-parca-p-467589114';
//'https://www.trendyol.com/lego/star-wars-501-klon-trooperlar-paketi-75345-6-yas-ve-uzeri-icin-yapim-seti-119-parca-p-467589114?boutiqueId=677589&merchantId=968';
Artisan::command('test-arzdigital', function (\App\Actions\HttpService $httpService) {
    Currency::syncTryRate();

    $url = 'https://lake.arzdigital.com/web/api/v1/pub/coins?type=fiat';
    $response = $httpService->sendWithCache('get', $url);
    $response = json_decode($response, true);

    $lyre = collect($response['data'])->keyBy('symbol')->get('TRY')['toman'];
    $dirham = collect($response['data'])->keyBy('symbol')->get('AED')['toman'];
    dump($lyre, $dirham);
});

Artisan::command('test-decathlon', function () {
    app(\App\Actions\SyncVariationsActions::class)->execute(Variation::find(5159));
//    \App\Jobs\SeedVariationsForProductJob::dispatchSync(Product::find(5159));
});

Artisan::command('test-seed-decathlon', function () {
    $jobs = Variation::query()
        ->where(function (Builder $query) {
            $query
                ->whereNot('url', '=', '')
                ->where(function (Builder $query) {
                    $query
                        ->whereNotNull('own_id')
                        ->orWhere('item_type', '=', Product::PRODUCT_UPDATE);
                });
        })
        ->where('updated_at', '<', now()->subDay())
        ->whereRelation('product', 'decathlon_url', '!=', '')
        ->limit(20)
        ->get()
        ->map(function (Variation $variation) {
            return new SyncVariationsJob($variation);
        });

    Bus::batch($jobs)
        ->then(fn() => Log::info('All variations updated successfully.'))
        ->catch(fn() => Log::error('Some jobs failed.'))
        ->name('Import Variations')
        ->dispatch();
});

Artisan::command('test-seed-decathlon', function () {
    \App\Jobs\SeedVariationsForProductJob::dispatchSync(Product::find(6523));
});

Artisan::command('test-sync-decathlon', function () {
    SyncVariationsJob::dispatchSync(Variation::find(5116));
});

Artisan::command('test-trendyol-seed-api', function () {
    \App\Jobs\SeedVariationsForProductJob::dispatchSync(Product::find(8304));
});

Artisan::command('test-trendyol-sync-api', function () {
    app(\App\Actions\SyncVariationsActions::class)->execute(Variation::find(2767));
});

Artisan::command('test-general {variation}', function ($variation) {
    app(\App\Actions\SyncVariationsActions::class)->execute(Variation::find($variation));
});

Artisan::command('test-seed', function () {
    $trP = Product::find(1253246);
//    $dep = Product::find(6520);

    \App\Jobs\SeedVariationsForProductJob::dispatchSync($trP);
//    \App\Jobs\SeedVariationsForProductJob::dispatchSync($dep);

    app(\App\Jobs\SeedVariationsForProductJob::class)->dispatchSync($trP);
//    app(\App\Jobs\SeedVariationsForProductJob::class)->dispatchSync($dep);
});

Artisan::command('test-sync-decathlon-unavailable', function () {
    $v = Variation::query()
        ->whereNot('status', Variation::AVAILABLE)
        ->where('source', Product::SOURCE_DECATHLON)
        ->limit(10)
        ->get();

    dump($v->toArray());

    $v->each(function (Variation $variation) {
        app(SyncVariationsActions::class)->execute($variation);
    });
});

Artisan::command('sync-all-decathlon', function () {
    $jobs = Variation::query()
        ->where(function (Builder $query) {
            $query
                ->whereNot('url', '=', '')
                ->where('source', Product::SOURCE_DECATHLON)
                ->whereNot('status', Variation::AVAILABLE)
                ->where(function (Builder $query) {
                    $query
                        ->whereNotNull('own_id')
                        ->orWhere('item_type', '=', Product::PRODUCT_UPDATE);
                });
        })
        ->get()
        ->map(function (Variation $variation) {
            return new SyncVariationsJob($variation);
        });

    Bus::batch($jobs)
        ->then(fn() => Log::info('All variations updated successfully.'))
        ->catch(fn() => Log::error('Some jobs failed.'))
        ->name('sync all decathlon variations')
        ->dispatch();
});

Artisan::command('resync-all-decathlon', function () {
    $products = Product::query()
        ->whereNot('decathlon_url', '=', '')
        ->get();

    $products->each(function (Product $product) {
        $cacheKey = md5('response' . $product->id);
        if (Cache::has($cacheKey)) {
            app(\App\Actions\SeedVariationsForDecathlonAction::class)->execute($product->id);
        }
    });
});

Artisan::command('sync-all-trendyol', function () {
    $products = Product::query()
        ->where('trendyol_source', '!=', '')
        ->where('decathlon_url', '=', '')
        ->get();
    $jobs = $products
        ->map(function (Product $product) {
            return new \App\Jobs\SeedVariationsForProductJob($product);
        });

    Bus::batch($jobs)
        ->then(fn() => Log::info('All variations updated successfully.'))
        ->catch(fn() => Log::error('Some jobs failed.'))
        ->name('sync all decathlon variations')
        ->dispatch();
});

Artisan::command('seed-elele', function () {
    $jobs = Product::query()
        ->where('elele_source', '!=', '')
        ->get()
        ->each(fn($product) => SeedVariationsForProductJob::dispatchSync($product));
});

Artisan::command('sync-elele', function () {
    $jobs = Variation::query()
        ->where('source', Product::SOURCE_Elele)
        ->get()
        ->each(fn($v) => SyncVariationsJob::dispatchSync($v));
});

Artisan::command('resync-elele', function () {
    $jobs = [];
    foreach (Variation::query()->where('source', Product::SOURCE_Elele)->get() as $variation) {
        if ($variation->status == Variation::AVAILABLE) {
            $updateData = ZitaziUpdateDTO::createFromArray([
                'stock_quantity' => $variation->stock,
                'price' => $variation->rial_price,
            ]);
        } else {
            $updateData = ZitaziUpdateDTO::createFromArray([
                'stock_quantity' => 0,
            ]);
        }
        $jobs[] = new SyncZitaziJob($variation, $updateData);
    }

    Bus::batch($jobs)
        ->then(fn() => Log::info('All variations synced with zitazi successfully.'))
        ->catch(fn() => Log::error('Some sync zitazi jobs failed.'))
        ->name('Sync Zitazi variations')
        ->dispatch();
});

Artisan::command('batch-update-test', function () {
    $variationsData = [];
    $productSData = [
        'update' => [

        ]
    ];
    $woo = WoocommerceService::getClient();

    $products = Product::query()
        ->withWhereHas('variations', function (Builder|HasMany $query) {
            $query->where(function (Builder $query) {
                $query
                    ->whereNot('own_id', '')
                    ->orWhere('item_type', Product::PRODUCT_UPDATE);
            });
        })
        ->whereNot('promotion', '=', 1)
        ->whereIn('id', [6523, 6525])
        ->get()
        ->each(function (Product $product) use (&$variationsData, &$productSData) {
            $massVariationUpdateData = [
                'product_id' => $product->own_id,
                'data' => [
                    'update' => [

                    ]
                ]
            ];
            foreach ($product->variations as $variation) {
                if ($variation->status == Variation::AVAILABLE) {
                    $dto = ZitaziUpdateDTO::createFromArray([
                        'stock_quantity' => $variation->stock,
                        'price' => $variation->rial_price,
                    ]);
                } else {
                    $dto = ZitaziUpdateDTO::createFromArray([
                        'stock_quantity' => 0,
                    ]);
                }

                $stockStatus = ZitaziUpdateDTO::OUT_OF_STOCK;
                if ($dto->stock_quantity > 0) {
                    $stockStatus = ZitaziUpdateDTO::IN_STOCK;
                }

                $data = $dto->getUpdateBody();

                $data['stock_status'] = $stockStatus;

                if (!empty($dto->price)) {
                    $data['sale_price'] = null;
                    $data['regular_price'] = '' . $dto->price;
                }

                if ($variation->item_type == Product::PRODUCT_UPDATE and empty($variation->own_id)) {
                    $data['id'] = $variation->product->own_id;
                    $productSData['update'][] = $data;
                } elseif (!empty($variation->own_id)) {
                    $data['id'] = $variation->own_id;
                    $massVariationUpdateData['data']['update'][] = $data;
                } else {
                    return;
                }
                $variationsData[] = $massVariationUpdateData;
            }
        });

//    foreach (collect($productSData)->chunk(10) as $chunk) {
//        $response = $woo->post("products/batch",$productSData);
//        Log::info('response batch product',[
//            'response' => $response,
//        ]);
//    }
// 6520

    dd(json_encode($variationsData));
    $unavailableVariations = [];
    foreach ($variationsData as $variation) {
        $url = "products/{$variation['product_id']}/variations/batch";
        $data = $variation['data'];
        dd($data);
        $response = $woo->post($url, $data);
        foreach ($response->update as $responseData) {
            if (!empty($responseData->error)) {
                Log::error('error in zitazi sync', [
                    'response' => json_encode($responseData),
                    'data' => $variation['data'],
                    'url' => $url,
                ]);
//                $unavailableVariations[] = $variation
            } else {
                Log::info(
                    "batch-variation-update",
                    [
                        'body' => $data,
                        'product' => $variation['product_id'],
                        'response' => json_encode($response),
                    ]
                );
            }
        }
//        if ($response->sy)
    }

//    Log::error('WooCommerce error variation', [
//        'code' => $json['code'] ?? 'unknown',
//        'message' => $json['message'] ?? 'No message',
//        'variation_id' => $variation->id,
//        'json' => $json,
//        'body' => $body
//    ]);
//
//    $variation->update([
//        'status' => Variation::UNAVAILABLE_ON_ZITAZI,
//    ]);

});

Artisan::command('sazkala-test', function () {
    $response = HttpService::getSazKalaData('https://sazkala.com/product/gl-tribute-asat-classic-bluesboy-semi-hollow-rw-red-burst/');
    $crawler = new Crawler($response);
    $price = $crawler->filter('meta[property="product:price:amount"]')->first()->attr('content');
    $stock = $crawler->filter('meta[property="product:availability"]')->first()->attr('content');
    $stock = $stock === 'instock' ? 88 : 0;

    $data = [
        'price' => $price,
        'stock' => $stock,
        'rial_price' => $price,
        'status' => Variation::AVAILABLE
    ];

    dd($data);
});

Artisan::command('sazkala-seed', function () {
    $products = Product::query()
        ->where('sazkala_source', '!=', '')
        ->get();

    foreach ($products as $product) {
        SeedVariationsForProductJob::dispatchSync($product);
    }

});

Artisan::command('seed-tr {id}', function ($id) {
    SeedVariationsForProductJob::dispatchSync(Product::firstWhere('own_id', $id));
});

Artisan::command('sync-zitazi {id}', function ($id) {


});

Artisan::command('sync-zitazi-all {id}', function ($id) {
    $product = Product::firstWhere('own_id', $id);

    foreach ($product->variations as $variation) {
        $updateData = ZitaziUpdateDTO::createFromArray([
            'stock_quantity' => $variation->stock,
            'price' => $variation->rial_price
        ]);

        SyncZitaziJob::dispatchSync($variation, $updateData);
    }


});

Artisan::command('temp-del', function () {
    $data = [
        883057,
        771203,
        771204,
        771206,
        771207,
        771208,
        771209,
        771210,
        771205,
    ];

    foreach ($data as $item) {
        $variation = Variation::firstWhere([
            'own_id' => $item,
        ]);

        if ($variation) {
            $updateData = ZitaziUpdateDTO::createFromArray([
                'stock_quantity' => 0,
            ]);

            SyncZitaziJob::dispatch($variation, $updateData);

            $variation->update(['own_id' => 0]);
        }

    }
});

Artisan::command('satre-test', function () {
    app(\Database\Seeders\ProductSeeder::class)->seedSatreProducts();
    foreach (Product::where('base_source', Product::SATRE) as $product) {
        SeedVariationsForProductJob::dispatchSync($product);
    }
});
