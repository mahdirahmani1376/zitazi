<?php

namespace App\Filament\Resources\Variations\Pages;

use App\Actions\Filament\GetSourceDataAction;
use App\Actions\Filament\SendUpdateRequestAction;
use App\Actions\Filament\SyncAndUpdateProductButtonAction;
use App\Filament\Resources\Variations\VariationResource;
use App\Models\Variation;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVariation extends ViewRecord
{
    protected static string $resource = VariationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('sync and update')
                ->icon('heroicon-m-arrow-path')
                ->color('success')
                ->action(function (Variation $variation) {
                    SyncAndUpdateProductButtonAction::execute($variation->product);
                })
                ->successNotificationTitle('Record Updated')
                ->failureNotificationTitle(function (int $successCount, int $totalCount): string {
                    return 'Failed to update records';
                }),
            Action::make('get source data')
                ->icon('heroicon-m-arrow-path')
                ->color('info')
                ->action(function (Variation $variation) {
                    GetSourceDataAction::executeVariation($variation);
                })
                ->after(fn() => $this->dispatch('refreshLogs'))
                ->successNotificationTitle('Record Updated')
                ->failureNotificationTitle(function (int $successCount, int $totalCount): string {
                    return 'Failed to update records';
                }),
            Action::make('send update request')
                ->icon('heroicon-m-arrow-path')
                ->color('primary')
                ->action(function (Variation $variation) {
                    SendUpdateRequestAction::execute($variation);
                })
                ->successNotificationTitle('Record Updated')
                ->failureNotificationTitle(function (int $successCount, int $totalCount): string {
                    return 'Failed to update records';
                })
        ];
    }
}
