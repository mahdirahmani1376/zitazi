<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Widgets\ChartWidget;

class DecathlonProductSyncStatusReport extends ChartWidget
{
    protected ?string $heading = 'وضعیت آپدیت محصولات دکتلون';

    protected string $color = 'success';

    protected function getData(): array
    {
        $data = Product::selectRaw('count(*) as total,sync_status')
            ->groupBy('sync_status')
            ->whereNotNull('decathlon_url')
            ->orWhereRaw('trim(decathlon_url) != ""')
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
