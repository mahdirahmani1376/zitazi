<?php

namespace App\Exports;

use App\Models\Variation;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DecalthonVariationExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Variation::with('product')
            ->get();
    }

    public function headings(): array
    {
        return [
            'شناسه تنوع',
            'شناسه محصول',
            'شناسه زیتازی',
            'شناسه تنوع دکلتون',
            'قیمت ',
            'قیمت ریالی',
            'لینک تنوع',
            'موجودی',
            'سایز',
            'زمان آپدیت',
        ];
    }

    public function map($row): array
    {
        /** @var variation $row */
        return [
            'id' => $row->id,
            'product_id' => $row->product_id,
            'zitazi_id' => $row->product->own_id,
            'sku' => $row->sku,
            'price' => $row->price,
            'rial_price' => $row->rial_price,
            'url' => $row->url,
            'stock' => $row->stock,
            'size' => $row->size,
            'updated_at' => $row->updated_at->toDateTimestring(),
        ];
    }
}
