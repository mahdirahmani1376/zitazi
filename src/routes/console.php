<?php

use App\Actions\HttpService;
use App\DTO\ZitaziUpdateDTO;
use App\Jobs\SyncZitaziJob;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Variation;
use App\Services\WoocommerceService;
use Illuminate\Bus\BatchRepository;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

Artisan::command('cancel-batch {batch}', function ($batch) {
    Container::getInstance()->make(BatchRepository::class)?->find($batch)?->cancel();
});

Artisan::command('test-arzdigital', function (HttpService $httpService) {
    Currency::syncTryRate();

    $url = 'https://lake.arzdigital.com/web/api/v1/pub/coins?type=fiat';
    $response = $httpService->sendWithCache('get', $url);
    $response = json_decode($response, true);

    $lyre = collect($response['data'])->keyBy('symbol')->get('TRY')['toman'];
    $dirham = collect($response['data'])->keyBy('symbol')->get('AED')['toman'];
    dump($lyre, $dirham);
});

Artisan::command('batch-update-test', function () {
    $variationsData = [];
    $productSData = [
        'update' => [

        ],
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

                    ],
                ],
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

                if (! empty($dto->price)) {
                    $data['sale_price'] = null;
                    $data['regular_price'] = ''.$dto->price;
                }

                if ($variation->item_type == Product::PRODUCT_UPDATE and empty($variation->own_id)) {
                    $data['id'] = $variation->product->own_id;
                    $productSData['update'][] = $data;
                } elseif (! empty($variation->own_id)) {
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
            if (! empty($responseData->error)) {
                Log::error('error in zitazi sync', [
                    'response' => json_encode($responseData),
                    'data' => $variation['data'],
                    'url' => $url,
                ]);
                //                $unavailableVariations[] = $variation
            } else {
                Log::info(
                    'batch-variation-update',
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

Artisan::command('sync-zitazi-all {id}', function ($id) {
    $product = Product::firstWhere('own_id', $id);

    foreach ($product->variations as $variation) {
        $updateData = ZitaziUpdateDTO::createFromArray([
            'stock_quantity' => $variation->stock,
            'price' => $variation->rial_price,
        ]);

        SyncZitaziJob::dispatchSync($variation, $updateData);
    }

});
