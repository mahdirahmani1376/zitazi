<?php

namespace App\Exports;

use App\Models\Product;
use App\Models\TorobProduct;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TorobProductsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $torobProducts = TorobProduct::all();
        $products = Product::whereIn('torob_id', $torobProducts->pluck('random_key'))->get()->keyBy('torob_id');

        foreach ($torobProducts as $torobProduct) {
            $torobProduct->setAttribute('product', $products->get($torobProduct->random_key));
        }

        return $torobProducts;
    }

    public function headings(): array
    {
        return [
            'شناسه',
            'شناسه زیتازی',
            'شناسه ترب',
            'دسته بندی',
            'برند',
            'مالک',
            'نام محصول',
            'نام محصول در ترب',
            'قیمت زیتازی',
            'موجودی',
            'رتبه در ترب',
            'تک فروشنده',
            'قیمت زیتازی نسبت به ترب',
            'منبع ترب',
            'کم ترین قیمت ترب',
            'قیمت زیتازی در ترب',
            'قیمت پیشنهادی ترب',
            'زمان آپدیت',
        ];
    }

    public function map($row): array
    {
        /** @var TorobProduct $row */
        return [
            'id' => $row->id,
            'zitazi_id' => $row->product?->own_id,
            'torob_id' => $row->random_key,
            'category' => $row->product?->category,
            'brand' => $row->product?->brand,
            'owner' => $row->product?->owner,
            'product_name' => $row->product?->product_name,
            'name1' => $row->name1,
            'zitazi_price' => $row->product?->rial_price,
            'stock' => $row->product?->stock,
            'zitazi_torob_ratio' => $row->productCompare?->zitazi_torob_ratio,
            'torob_url' => $row->torob_source,
            'torob_min_price' => $row->productCompare?->torob_min_price,
            'zitazi_torob_price' => $row->productCompare?->zitazi_torob_price,
            'zitazi_torob_price_recommend' => $row->productCompare?->zitazi_torob_price_recommend,
            'updated_at' => $row->updated_at->toDateTimestring(),
        ];
    }
}
