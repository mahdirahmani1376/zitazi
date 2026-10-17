<?php

namespace App\Exports;

use App\Models\Variation;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class NullVariationExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return Collection
     */
    public function collection()
    {
        return Variation::with('product')
            ->orderBy('product_id')
            ->whereNull('own_id')
            ->get();
    }

    public function headings(): array
    {
        return [
            'شناسه تنوع در وب سرویس',
            'مرجع',
            'شناسه محصول در وب سرویس',
            'نام محصول',
            'شناسه محصول زیتازی',
            'شناسه تنوع زیتازی',
            'شناسه تنوع دکتلون',
            'قیمت',
            'قیمت ریالی',
            'لینک تنوع',
            'موجودی',
            'سایز',
            'رنگ',
            'برند',
            'منبع',
            'زمان آپدیت',
            'وضعیت',
            'غیرفعال',
        ];
    }

    public function map($row): array
    {
        /** @var Variation $row */
        return [
            'id' => $row->id,
            'base_source' => $row->base_source,
            'product_id' => $row->product_id,
            'product_name' => $row->product?->product_name,
            'zitazi_product_id' => $row->product?->own_id,
            'zitazi_variation_id' => $row->own_id,
            'sku' => $row->sku,
            'price' => $row->price,
            'rial_price' => $row->rial_price,
            'url' => $row->url,
            'stock' => $row->stock,
            'size' => $row->size,
            'color' => $row->color,
            'brand' => $row->product?->brand,
            'source' => $row->source,
            'updated_at' => $row->updated_at?->toDateTimestring(),
            'status' => $row->status,
            'is_deleted' => $row->is_deleted,
        ];
    }
}
