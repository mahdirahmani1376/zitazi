<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\Variation;
use Filament\Widgets\ChartWidget;

class TrendyolVariationReport extends ChartWidget
{
    protected ?string $heading = 'وضعیت تنوع های ترندیول';
    protected string $color = 'success';

    protected function getData(): array
    {
        $data = Variation::query()
            ->selectRaw('status,count(*) as count')
            ->where('source', Product::SOURCE_TRENDYOL)
            ->groupBy('status')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'تعداد محصولات',
                    'data' => $data->map(fn($item) => $item->count),
                ],
            ],
            'labels' => $data->map(fn($item) => $item->status ?? 'نامشخص'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
