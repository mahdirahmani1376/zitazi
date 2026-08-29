<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CurrencyWidget;
use App\Filament\Widgets\DecathlonLogModelsReport;
use App\Filament\Widgets\DecathlonProductSyncStatusReport;
use App\Filament\Widgets\DecathlonVariationReport;
use App\Filament\Widgets\DecathlonVariationsStockReport;
use App\Filament\Widgets\SyncWidget;
use App\Filament\Widgets\TrendyolLogModelsReport;
use App\Filament\Widgets\TrendyolProductSyncStatusReport;
use App\Filament\Widgets\TrendyolVariationReport;
use App\Filament\Widgets\TrendyolVariationsStockReport;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            SyncWidget::class,
            CurrencyWidget::class,
            TrendyolVariationsStockReport::class,
            DecathlonVariationsStockReport::class,
            TrendyolVariationReport::class,
            DecathlonVariationReport::class,
            TrendyolProductSyncStatusReport::class,
            DecathlonProductSyncStatusReport::class,
            TrendyolLogModelsReport::class,
            DecathlonLogModelsReport::class,
        ];
    }
}
