<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Widgets\ChartWidget;

class TorobRankChart extends ChartWidget
{
    protected ?string $heading = 'توزیع رتبه محصولات در ترب';

    protected ?string $description = 'تعداد محصولات دارای شناسه ترب، به تفکیک رتبه فعلی';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $rankCounts = Product::query()
            ->whereNotNull('torob_id')
            ->where('torob_id', '!=', '')
            ->selectRaw('torob_rank, COUNT(*) as products_count')
            ->groupBy('torob_rank')
            ->orderBy('torob_rank')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'تعداد محصولات',
                    'data' => $rankCounts
                        ->pluck('products_count')
                        ->map(fn (int|string $count): int => (int) $count)
                        ->all(),
                ],
            ],
            'labels' => $rankCounts
                ->map(fn (Product $product): string => $product->torob_rank === null
                    ? 'بدون رتبه'
                    : 'رتبه '.$product->torob_rank
                )
                ->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
