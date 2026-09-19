<?php

namespace App\Filament\Widgets;

use App\Models\Currency;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class CurrencyRefreshWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.currency-refresh';

    public function refreshAction(): Action
    {
        return Action::make('refresh')
            ->label('بروزرسانی نرخ ارزها')
            ->icon('heroicon-m-arrow-path')
            ->color('primary')
            ->action(function () {
                Cache::forget('try_rate');
                Cache::forget('eur_rate');

                $tryRate = Currency::syncTryRate();
                $eurRate = Currency::syncEurRate();

                Notification::make()
                    ->title('نرخ‌ها بروزرسانی شدند')
                    ->body("لیر: {$tryRate} | یورو: {$eurRate}")
                    ->success()
                    ->send();
            });
    }
}
