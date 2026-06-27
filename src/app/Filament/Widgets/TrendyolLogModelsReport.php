<?php

namespace App\Filament\Widgets;

use App\Models\LogModel;
use App\Models\Product;
use Filament\Widgets\ChartWidget;

class TrendyolLogModelsReport extends ChartWidget
{
    protected ?string $heading = 'وضعیت محصولات ترندیول';
    protected string $color = 'success';

    protected function getData(): array
    {
        $data = Product::query()
            ->selectRaw('count(*) as count')
            ->addSelect([
                'message' => LogModel::query()
                    ->select('message')
                    ->whereColumn('log_models.product_id', '=', 'products.id')
                    ->orderByDesc('created_at')
                    ->limit(1)
            ])
            ->whereNotNull('trendyol_source')
            ->groupByRaw('message')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'تعداد محصولات',
                    'data' => $data->map(fn($item) => $item->count),
                ],
            ],
            'labels' => $data->map(fn($item) => $item->message ?? 'نامشخص'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
