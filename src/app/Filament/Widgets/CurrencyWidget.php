<?php

namespace App\Filament\Widgets;

use App\Models\Currency;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CurrencyWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('نرخ لیر', Currency::syncTryRate() ?? '—'),
            Stat::make('نرخ درهم', Currency::syncDirhamTryRate() ?? '—'),
            Stat::make(
                'آخرین بروزرسانی',
                optional(Currency::latest()->first())->updated_at?->diffForHumans()
            )
        ];
    }
}
