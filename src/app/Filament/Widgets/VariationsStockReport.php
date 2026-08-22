<?php

namespace App\Filament\Widgets;

use App\Models\Variation;
use Filament\Widgets\ChartWidget;

class VariationsStockReport extends ChartWidget
{
    protected ?string $heading = 'وضعیت موجودی تنوع ها';

    protected string $color = 'success';

    protected function getData(): array
    {
        $data = Variation::selectRaw('count(*) as total,stock,source')->groupBy(['source', 'stock'])->get();

        return [
            'datasets' => [
                [
                    'label' => 'تعداد محصولات',
                    'data' => $data->map(fn($item) => $item->total),
                ],
            ],
            'labels' => $data->map(fn($item) => $item->source . '-' . $item->stock ?? 'نامشخص'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
