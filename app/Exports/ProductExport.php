<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;


class ProductExport implements FromCollection,WithHeadings,WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Product::with('productCompare')
            ->get();
    }
    public function headings(): array
    {
        return [
            'شناسه',
            'شناسه زیتازی',
            'آدرس ترندیول',
            'قیمت مرجع ترندیول',
            'موجودی',
            'قیمت زیتازی',
            'شناسه دیجی کالا',
            'قیمت زیتازی در دیجی کالا',
            'پایین ترین قیمت دیجی کالا',
            'قیمت پیشنهادی دیجی کالا',
            'منبع ترب',
            'کم ترین قیمت ترب',
            'قیمت زیتازی در ترب',
            'قیمت پیشنهادی ترب',
            'زمان آپدیت',
        ];
    }

    public function map($row): array
    {
        return [
            'id' => $row->id,
            'zitazi_id' =>$row->own_id,
            'trendyol_url' =>$row->trendyol_source,
            'price' =>$row->price,
            'stock' =>$row->stock,
            'zitazi_price' =>$row->rial_price,
            'digikala_dkp' =>$row->digikala_source,
            'digikala_zitazi_price' => $row->productCompare?->digikala_zitazi_price,
            'digikala_min_price' => $row->productCompare?->digikala_min_price,
            'zitazi_digikala_price_recommend' => $row->productCompare?->zitazi_digikala_price_recommend,
            'torob_url' =>$row->torob_source,
            'torob_min_price' => $row->productCompare?->torob_min_price,
            'zitazi_torob_price' => $row->productCompare?->zitazi_torob_price,
            'zitazi_torob_price_recommend' => $row->productCompare?->zitazi_torob_price_recommend,
            'updated_at' => $row->updated_at->toDateTimestring()
        ];
     }
}
