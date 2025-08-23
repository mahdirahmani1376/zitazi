<?php

use App\Actions\Crawler\BaseVariationCrawler;
use App\DTO\ZitaziUpdateDTO;
use App\Models\Product;
use App\Models\SyncLog;
use App\Models\Variation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

Route::get('amazon-list', function () {
//    return \Illuminate\Support\Facades\Response::json([
//        'data' => [
//            'https://www.amazon.ae/dp/B09NLFPD4Q',
//            'https://www.amazon.ae/dp/B09HL1JM1N'
//        ]
//    ]);
    $results = [];

    $amazonVariations = Variation::where([
        'source' => Product::SOURCE_AMAZON,
    ])->get();

    foreach ($amazonVariations as $variation) {
        $results[] = [
            'id' => $variation->id,
            'url' => "https://www.amazon.ae/dp/{$variation->url}"
        ];
    }

    return Response::json([
        'data' => $results
    ]);

});

Route::put('/variations/{variation}/update', function (
    Variation            $variation,
    Request              $request,
    BaseVariationCrawler $baseVariationCrawler
) {

    $rialPrice = \App\Models\Currency::convertDirhamToRial($request->get('price'));

    $data = [
        'price' => $request->get('price'),
        'stock' => $request->get('stock'),
        'rial_price' => $rialPrice,
    ];

    $baseVariationCrawler->updateVariationAndLog($variation, $data);

    $dto = ZitaziUpdateDTO::createFromArray([
        'price' => $rialPrice,
        'stock_quantity' => $request->get('stock'),
    ]);

    $baseVariationCrawler->syncZitazi($variation, $dto);

    return \Illuminate\Support\Facades\Response::json(
        [
            'status' => 'success'
        ]
    );
});

Route::get('decathlon-list', function () {
    return Response::json([
        'data' => Product::query()
            ->whereNot('decathlon_url', '=', '')
            ->where('trendyol_source', '=', '')
            ->orderBy('updated_at', 'asc')
            ->paginate()
    ]);
});

Route::post('store-decathlon', function (Request $request) {
    foreach ($request->data as $result) {
        if (!$result['success']) {
            $product = Product::find($result['product_id']);
            foreach ($product->variations as $variation) {
                $oldStock = $variation->stock;
                $oldPrice = $variation->price;

                $variation->update([
                    'status' => Variation::UNAVAILABLE,
                    'stock' => 0,
                ]);

                if ($oldStock != $variation->stock) {
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
            \Illuminate\Support\Facades\Log::error('decathlon-sync-error', [
                'result' => $result,
                'product_id' => $product->id
            ]);
            return response()->json(['status' => 'failed']);
        }
        $cacheKey = md5('response' . $result['product_id']);
        Cache::put($cacheKey, $result, now()->addDays(2));
        app(\App\Actions\SeedVariationsForDecathlonAction::class)->execute($result['product_id']);
    }
    return response()->json(['status' => 'ok']);
});


