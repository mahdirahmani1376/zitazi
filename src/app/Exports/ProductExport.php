<?php

namespace App\Exports;
ini_set('memory_limit', '800M');

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductExport implements FromQuery, WithHeadings, WithMapping
{

    public function query()
    {
        return Product::query();
    }

    public function headings(): array
    {
        return [
            'شناسه',
            'مرجع',
            'شناسه زیتازی',
            'دسته بندی',
            'برند',
            'مالک',
            'نام محصول',
            'آدرس ترندیول',
            'قیمت مرجع ترندیول',
            'موجودی',
            'قیمت زیتازی',
            'قیمت زیتازی نسبت به دیجی کالا',
            'قیمت زیتازی نسبت به ترب',
            'شناسه دیجی کالا',
            'قیمت زیتازی در دیجی کالا',
            'پایین ترین قیمت دیجی کالا',
            'قیمت پیشنهادی دیجی کالا',
            'زمان آپدیت',
        ];
    }

    public function map($row): array
    {
        /** @var Product $row */
        return [
            'id' => $row->id,
            'base_source' => $row->base_source,
            'zitazi_id' => $row->own_id,
            'category' => $row->category,
            'brand' => $row->brand,
            'owner' => $row->owner,
            'product_name' => $row->product_name,
            'trendyol_url' => $row->trendyol_source,
            'price' => $row->price,
            'stock' => $row->stock,
            'zitazi_price' => $row->rial_price,
            'zitazi_digi_ratio' => $row->productCompare?->zitazi_digi_ratio,
            'zitazi_torob_ratio' => $row->productCompare?->zitazi_torob_ratio,
            'digikala_dkp' => $row->digikala_source,
            'digikala_zitazi_price' => $row->productCompare?->digikala_zitazi_price,
            'digikala_min_price' => $row->productCompare?->digikala_min_price,
            'zitazi_digikala_price_recommend' => $row->productCompare?->zitazi_digikala_price_recommend,
            'updated_at' => $row->updated_at?->toDateTimestring(),
        ];
    }
}
