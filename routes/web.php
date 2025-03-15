<?php

use App\Actions\ProductCompareAction;
use App\Actions\Top100Action;
use App\Exports\DecalthonVariationExport;
use App\Exports\ProductExport;
use App\Jobs\updateJob;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;

Route::get('/', function () {
    return view('dashboard');
});

Route::get('/report', function () {
    $digikala = \App\Models\Report::query()->where('source', 'digikala')->orderByDesc('created_at')->first();
    $torob = \App\Models\Report::query()->where(['source' => 'torob'])->orderByDesc('created_at')->first();

    $data = [
        'digikala' => $digikala,
        'torob' => $torob,
    ];

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

    return 'آپدیت محصولات در حال انجام است';
})->name('products.update');

Route::get('top-100', Top100Action::class)->name('top-100');

Route::get('/variation-download', function () {
    $now = now()->toDateTimeString();

    return Excel::download(new DecalthonVariationExport, "variations_{$now}.xlsx");
})->name('variations.download');
