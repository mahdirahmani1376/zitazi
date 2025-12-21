<?php

namespace App\Exports;
ini_set('memory_limit', '800M');

use App\Models\Variation;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FillamentVariationExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        public $records
    )
    {
    }

    public function query()
    {
        return Variation::with('product')
            ->whereIn('id', $this->records->pluck('id'))
            ->orderBy('product_id');
    }

    public function headings(): array
    {
        return [
            'شناسه تنوع در وب سرویس',
            'شناسه محصول در وب سرویس',
            'مرجع',
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
        /** @var variation $row */
        return [
            'id' => $row->id,
            'product_id' => $row->product_id,
            'base_source' => $row->base_source,
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
            'updated_at' => $row->updated_at->toDateTimestring(),
            'status' => $row->status,
            'is_deleted' => $row->is_deleted,
        ];

    }
}
