<?php

use App\Actions\ProductCompareAction;
use App\Actions\Top100Action;
use App\Exports\DecathlonVariationExport;
use App\Exports\ProductExport;
use App\Exports\TorobProductsExport;
use App\Imports\ImportDecathlonVariation;
use App\Jobs\updateJob;
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
    updateJob::dispatch();

    return back()->with('success', 'آپدیت محصولات در حال انجام است');

})->name('products.update');

Route::get('top-100', Top100Action::class)->name('top-100');

Route::get('/variation-download', function () {
    $now = now()->toDateTimeString();

    return Excel::download(new DecathlonVariationExport, "variations_{$now}.xlsx");
})->name('variations.download');

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

    Excel::import(new ImportDecathlonVariation, $request->file('file'));

    return back()->with('success', 'فایل با موفقیت ایمپورت شد');
})->name('variations.import');

Route::get('ci-cd', function () {
    return 'hello ci-cd';
});

Route::get('redis-test', function () {
    Redis::set('test', 'Hello, Redis!');
    echo Redis::get('test');
});
