<?php

namespace App\Filament\Resources\Products\Pages;

use App\Actions\Filament\SyncAndUpdateProductButtonAction;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('sync')
                ->icon('heroicon-m-arrow-path')
                ->color('success')
                ->action(function (Product $product) {
                    SyncAndUpdateProductButtonAction::execute($product);
                })
                ->successNotificationTitle('Record Updated')
                ->failureNotificationTitle(function (int $successCount, int $totalCount): string {
                    return 'Failed to update any record';
                })
        ];
    }
}
