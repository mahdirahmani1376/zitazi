<?php

namespace App\Filament\Resources\Variations\Pages;

use App\Actions\SyncAndUpdateProductButtonAction;
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
            Action::make('sync')
                ->icon('heroicon-m-arrow-path')
                ->color('success')
                ->action(function (Variation $variation) {
                    SyncAndUpdateProductButtonAction::execute($variation->product);
                })
                ->successNotificationTitle('Record Updated')
                ->failureNotificationTitle(function (int $successCount, int $totalCount): string {
                    return 'Failed to update records';
                })
        ];
    }
}
