<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Widgets\ChartWidget;

class TrendyolProductSyncStatusReport extends ChartWidget
{
    protected ?string $heading = 'وضعیت آپدیت محصولات ترندیول';

    protected string $color = 'success';

    protected function getData(): array
    {
        $data = Product::selectRaw('count(*) as total,sync_status')
            ->groupBy('sync_status')
            ->whereNotNull('trendyol_source')
            ->orWhereRaw('trim(trendyol_source) != ""')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'تعداد محصولات',
                    'data' => $data->map(fn($item) => $item->total),
                ],
            ],
            'labels' => $data->map(fn($item) => $item->sync_status ?? 'نامشخص'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
