<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CurrencyWidget;
use App\Filament\Widgets\DecathlonLogModelsReport;
use App\Filament\Widgets\DecathlonProductSyncStatusReport;
use App\Filament\Widgets\DecathlonVariationReport;
use App\Filament\Widgets\TrendyolLogModelsReport;
use App\Filament\Widgets\TrendyolProductSyncStatusReport;
use App\Filament\Widgets\TrendyolVariationReport;
use App\Filament\Widgets\VariationsStockReport;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            CurrencyWidget::class,
            VariationsStockReport::class,
            TrendyolVariationReport::class,
            DecathlonVariationReport::class,
            TrendyolProductSyncStatusReport::class,
            DecathlonProductSyncStatusReport::class,
            TrendyolLogModelsReport::class,
            DecathlonLogModelsReport::class,
        ];
    }
}
