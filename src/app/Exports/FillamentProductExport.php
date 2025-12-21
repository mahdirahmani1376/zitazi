<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

ini_set('memory_limit', '800M');

class FillamentProductExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(public $records)
    {
    }

    public function query()
    {
        return Product::query()
            ->whereIn('id', $this->records->pluck('id'));
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
            'منبع ترب',
            'کم ترین قیمت ترب',
            'قیمت زیتازی در ترب',
            'قیمت پیشنهادی ترب',
            'قیمت حال حاظر در ترب',
            'رتبه ترب',
            'تک فروشنده',
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
            'torob_url' => $row->torob_source,
            'torob_min_price' => $row->productCompare?->torob_min_price,
            'zitazi_torob_price' => $row->productCompare?->zitazi_torob_price,
            'zitazi_torob_price_recommend' => $row->productCompare?->zitazi_torob_price_recommend,
            'torob_price' => $row?->torob_product?->price,
            'rank' => $row?->torob_product?->rank,
            'clickable' => $row?->torob_product?->clickable,
            'updated_at' => $row->updated_at?->toDateTimestring(),
        ];
    }
}
