<?php

use App\Actions\ProductCompareAction;
use App\Actions\Top100Action;
use App\Exports\NullVariationExport;
use App\Exports\OutOfStockExport;
use App\Exports\ProductExport;
use App\Exports\SyncLogExport;
use App\Exports\TorobProductsExport;
use App\Exports\VariationExport;
use App\Imports\ImportDecathlonVariation;
use App\Jobs\SyncProductJob;
use App\Jobs\SyncVariationsJob;
use App\Jobs\UpdateJob;
use App\Models\Product;
use App\Models\Report;
use App\Models\TorobProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;

Route::get('/', function () {
    return view('dashboard');
});

Route::get('/report', function () {
    $uniqueCats = Report::query()
        ->whereNotNull('zitazi_category')
        ->distinct()
        ->pluck('zitazi_category');

    $data = [];

    foreach ($uniqueCats as $category) {
        $digikala = Report::query()
            ->where('zitazi_category', $category)
            ->where('source', 'digikala')
            ->latest('created_at')
            ->first();

        $digikala->setAttribute('top_digikala_sub_categories', $digikala->topDigikalaSubCategories());

        $torob = Report::query()
            ->where('zitazi_category', $category)
            ->where('source', 'torob')
            ->latest('created_at')
            ->first();

        $torob->setAttribute('top_torob_sub_categories', $digikala->topTorobSubCategories());

        $data[$category] = [
            'digikala' => $digikala,
            'torob' => $torob,
        ];
    }

    return view('report', [
        'data' => $data,
    ]);

})->name('products.report');

Route::get('/compare', ProductCompareAction::class)->name('products.compare');

Route::get('/product-download', function () {
    $now = now()->toDateTimeString();

    return Excel::download(new ProductExport, "products_{$now}.xlsx");
})->name('products.download');

Route::get('products', function () {
    return view('product');
});

Route::get('dashboard', function () {
    return view('dashboard');
});

Route::get('/update-products', function () {
    if (Cache::has('special-link-lock')) {
        return back()->with('success', 'آپدیت محصولات در حال انجام است');
    }

    Cache::put('special-link-lock', true, 3600 * 3);

    UpdateJob::dispatch();
    return back()->with('success', 'آپدیت محصولات در حال انجام است');

})->name('products.update');

Route::get('top-100', Top100Action::class)->name('top-100');

Route::get('/variation-download', function () {
    $now = now()->toDateTimeString();

    return Excel::download(new VariationExport, "variations_{$now}.xlsx");
})->name('variations.download');

Route::get('/null-variation-download', function () {
    $now = now()->toDateTimeString();

    return Excel::download(new NullVariationExport, "null_variations_{$now}.xlsx");
})->name('null-variations.download');

Route::get('/torob-products', function () {
    return view('torob-products', [
        'data' => TorobProduct::all(),
    ]);
})->name('torob-products.index');

Route::get('/download-torob-products', function () {
    $now = now()->toDateTimeString();

    return Excel::download(new TorobProductsExport, "variations_{$now}.xlsx");
})->name('torob-products.download');

Route::post('import', function (Request $request) {
    $request->validate([
        'file' => 'required|mimes:xlsx,csv,xls|max:2048',
    ]);

    $errors = session('import_errors') ?? [];
    Session::forget('import_errors'); // clear it after reading

    Excel::import(new ImportDecathlonVariation, $request->file('file'));

    return back()
        ->with('success', 'با موفقیت انجام شد')
        ->with('error', $errors ?? []);

})->name('variations.import');

Route::get('ci-cd', function () {
    return 'hello ci-cd';
});

Route::get('redis-test', function () {
    Redis::set('test', 'Hello, Redis!');
    echo Redis::get('test');
});

Route::get('/sync-logs-download', function () {
    $now = now()->toDateTimeString();
    return Excel::download(new SyncLogExport, "sync_logs_{$now}.xlsx");
})->name('sync-logs.download');

Route::get('/out-of-stock-logs-download', function () {
    $now = now()->toDateTimeString();
    return Excel::download(new OutOfStockExport, "sync_logs_{$now}.xlsx");
})->name('out-of-stock-logs.download');

Route::post('update-product', function (Request $request) {
    $product = Product::with('variations')->where([
        'own_id' => $request->get('own_id')
    ])->firstOrFail();

    if (!$product->has('variations')) {
        SyncProductJob::dispatchSync($product);
    } else {
        foreach ($product->variations as $variation) {
            if ($variation->type === Product::PRODUCT_UPDATE) {
                if ($product->belongsToTrendyol()) {
                    $variation->update([
                        'url' => $product->trendyol_source
                    ]);
                } else if ($product->belongsToDecalthon()) {
                    $variation->update([
                        'url' => $product->decathlon_url
                    ]);
                }

            }
            SyncVariationsJob::dispatchSync($variation);
        }
    }

    return back()->with('success', 'آپدیت محصول انجام شد');
})->name('product.update');

Route::post('seed-products', function () {
    dispatch(new \App\Jobs\SeedJob())->onQueue('sync')->afterResponse();
    return back()->with('success', 'بازخوانی محصولات در حال انجام است لطفا ۱۰ دقیقه صبر کنید');
})->name('products.seed');


Horizon::auth(function () {
    return true; // disables all auth checks
});

use Illuminate\Support\Facades\Storage;

Route::get('/movie', function () {
    $url = Storage::disk('local')->get('video_url.txt');
    return redirect(trim($url));
});

Route::match(['get', 'post'], '/submit-video', function (Request $request) {
    if ($request->isMethod('post')) {
        $url = $request->input('url');
        Storage::disk('local')->put('video_url.txt', $url);
        return "✅ Video link saved.";
    }

    return view('submit-video');
});

