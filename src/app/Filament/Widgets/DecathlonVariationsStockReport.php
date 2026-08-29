<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\Variation;
use Filament\Widgets\ChartWidget;

class DecathlonVariationsStockReport extends ChartWidget
{
    protected ?string $heading = 'وضعیت موجودی تنوع های دکتلون';

    protected string $color = 'success';

    protected function getData(): array
    {
        $data = Variation::selectRaw('count(*) as total,stock')
            ->groupBy('stock')
            ->where('source', Product::SOURCE_DECATHLON)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'تعداد محصولات',
                    'data' => $data->map(fn($item) => $item->total),
                ],
            ],
            'labels' => $data->map(fn($item) => $item->stock ?? 'نامشخص'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
