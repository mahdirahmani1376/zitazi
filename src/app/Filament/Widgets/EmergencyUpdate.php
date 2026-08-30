<?php

namespace App\Filament\Widgets;

use App\Jobs\EmergencySyncJob;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;

class EmergencyUpdate extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.emergency-update';

    public function executeTrendyolAction(): Action
    {
        return Action::make('executeTrendyol')
            ->label('ناموجود کردن اضطراری ترندیول')
            ->color('danger')
            ->icon('heroicon-o-play')
            ->requiresConfirmation()
            ->modalHeading('ناموجود کردن اضطراری ترندیول')
            ->modalDescription(
                'آیا مطمئن هستید که می‌خواهید این عملیات را اجرا کنید؟'
            )
            ->modalSubmitActionLabel('بله، اجرا کن')
            ->action(function () {
                EmergencySyncJob::dispatch(
                    source: 'tr',
                    invalid: true,
                );

                Notification::make()
                    ->title('درخواست ترندیول به صف اضافه شد')
                    ->success()
                    ->send();
            });
    }

    public function executeDecathlonAction(): Action
    {
        return Action::make('executeDecathlon')
            ->label('ناموجود کردن اضطراری دکتلون')
            ->color('danger')
            ->icon('heroicon-o-play')
            ->requiresConfirmation()
            ->modalHeading('ناموجود کردن اضطراری دکتلون')
            ->modalDescription(
                'آیا مطمئن هستید که می‌خواهید این عملیات را اجرا کنید؟'
            )
            ->modalSubmitActionLabel('بله، اجرا کن')
            ->action(function () {
                EmergencySyncJob::dispatch(
                    source: 'de',
                    invalid: true,
                );

                Notification::make()
                    ->title('درخواست دکتلون به صف اضافه شد')
                    ->success()
                    ->send();
            });
    }

}
