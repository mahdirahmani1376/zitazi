<?php

namespace App\Filament\Resources\Variations\Tables;

use App\Actions\Filament\SyncAndUpdateProductButtonAction;
use App\Exports\FillamentVariationExport;
use App\Models\Product;
use App\Models\Variation;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class VariationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product_id')
                    ->searchable(),
                TextColumn::make('price')
                    ->money()
                    ->sortable(),
                TextColumn::make('url')
                    ->limit(10)
                    ->tooltip(fn($record) => $record->url),
                TextColumn::make('stock')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('size'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rial_price')
                    ->money()
                    ->sortable(),
                TextColumn::make('own_id')
                    ->label('Zitazi variation id')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.own_id')
                    ->label('Woocommerce id')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('color'),
                TextColumn::make('item_type'),
                TextColumn::make('status'),
                IconColumn::make('is_deleted')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->multiple()
                    ->options(Variation::TableFilters()),
                SelectFilter::make('source')
                    ->multiple()
                    ->options(Product::getAllSourceLabels()),
                TernaryFilter::make('is_deleted'),
            ])
            ->recordActions([
                ViewAction::make(),
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
                    }),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
                BulkAction::make('bulk sync')
                    ->action(function (Collection $records) {
                        $records
                            ->unique('product_id')
                            ->each(function (Variation $record) {
                                SyncAndUpdateProductButtonAction::execute($record->product);
                            });
                    })
                    ->icon('heroicon-m-arrow-path')
                    ->color('success')
                    ->successNotificationTitle('Records Updated')
                    ->failureNotificationTitle(function (int $successCount, int $totalCount): string {
                        if ($successCount) {
                            return "{$successCount} of {$totalCount} Records updated";
                        }

                        return 'Failed to update any records';
                    }),
                BulkAction::make('excel export')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('info')
                    ->action(function (Collection $records) {
                        $now = now()->toDateTimeString();
                        return Excel::download(new FillamentVariationExport($records), "variations_{$now}.xlsx");
                    })
                    ->successNotificationTitle('export completed')
                    ->failureNotificationTitle(function (int $successCount, int $totalCount): string {
                        return 'Failed to export';
                    }),
            ]);
    }
}
