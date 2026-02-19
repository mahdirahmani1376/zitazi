<?php

namespace App\Filament\Resources\Products\Tables;

use App\Actions\Filament\SyncAndUpdateProductButtonAction;
use App\Exports\FillamentProductExport;
use App\Jobs\BulkSyncProductsJob;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('own_id')
                    ->searchable(),
                TextColumn::make('trendyol_source')
                    ->limit(10)
                    ->tooltip(fn($record) => $record->trendyol_source)
                ,
                TextColumn::make('base_source')
                ,
                TextColumn::make('digikala_source')
                    ->limit(10)
                    ->tooltip(fn($record) => $record->digikala_source)
                ,
                TextColumn::make('sazkala_source')
                    ->limit(10)
                    ->tooltip(fn($record) => $record->sazkala_source)
                ,
                TextColumn::make('torob_id')
                    ->limit(10)
                    ->tooltip(fn($record) => $record->torob_id)
                ,
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('min_price')
                    ->money()
                    ->sortable(),
                TextColumn::make('rival_min_price')
                    ->money()
                    ->sortable(),
                TextColumn::make('markup')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('category')
                ,
                TextColumn::make('brand')
                ,
                TextColumn::make('owner')
                ,
                TextColumn::make('product_name')
                ,
                TextColumn::make('decathlon_url')
                    ->limit(10)
                    ->tooltip(fn($record) => $record->decathlon_url)
                ,
                TextColumn::make('eth_source')
                    ->limit(10)
                    ->tooltip(fn($record) => $record->eth_source)
                ,
                TextColumn::make('decathlon_id')
                ,
                TextColumn::make('elele_source')
                    ->limit(10)
                    ->tooltip(fn($record) => $record->elele_source)
                ,
                TextColumn::make('matilda_source')
                    ->limit(10)
                    ->tooltip(fn($record) => $record->matilda_source)
                ,
                IconColumn::make('promotion')
                    ->boolean(),
                TextColumn::make('amazon_source')
                    ->limit(10)
                    ->tooltip(fn($record) => $record->amazon_source)
                ,
            ])
            ->filters([
                SelectFilter::make('category')
                    ->multiple()
                    ->options(
                        array_combine(
                            Product::query()->whereNotNull('category')->distinct('category')->pluck('category')->all(),
                            Product::query()->whereNotNull('category')->distinct('category')->pluck('category')->all()
                        ),
                    ),
                SelectFilter::make('brand')
                    ->multiple()
                    ->options(
                        array_combine(
                            Product::whereNotNull('brand')->distinct('brand')->pluck('brand')->all(),
                            Product::whereNotNull('brand')->distinct('brand')->pluck('brand')->all()
                        ),
                    ),
                SelectFilter::make('owner')
                    ->multiple()
                    ->options(
                        array_combine(
                            Product::whereNotNull('owner')->distinct('owner')->pluck('owner')->all(),
                            Product::whereNotNull('owner')->distinct('owner')->pluck('owner')->all(),
                        )
                    ),
                TernaryFilter::make('promotion'),
            ])
            ->recordActions([
                ViewAction::make(),
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
                    }),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('bulk sync')
                        ->action(function (Collection $records) {
                            $jobs = [];
                            $records->each(function (Product $record) use (&$jobs) {
                                $jobs[] = new BulkSyncProductsJob($record);
                            });
                            Bus::batch($jobs)
                                ->then(fn() => Log::info('All Bulk Sync Products finished successfully.'))
                                ->catch(fn() => Log::error('Some Bulk Sync Products failed.'))
                                ->name('Bulk Sync Products')
                                ->dispatch();
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
                            Notification::make()
                                ->success()
                                ->title('Export started')
                                ->body('The export job has been queued.')
                                ->send();
                            return Excel::download(new FillamentProductExport($records), "products_{$now}.xlsx");
                        })
                        ->successNotificationTitle('export completed')
                        ->failureNotificationTitle(function (int $successCount, int $totalCount): string {
                            return 'Failed to export';
                        }),
                    BulkAction::make('activate promotion')
                        ->icon('heroicon-m-arrow-path')
                        ->color('info')
                        ->action(function (Collection $records) {
                            $records->each(function (Product $record) {
                                $record->update([
                                    'promotion' => true
                                ]);
                            });
                        })
                        ->successNotificationTitle('toggle completed')
                        ->failureNotificationTitle(function (int $successCount, int $totalCount): string {
                            return 'Failed to toggle';
                        }),
                    BulkAction::make('deactivate promotion')
                        ->icon('heroicon-m-arrow-path')
                        ->color('info')
                        ->action(function (Collection $records) {
                            $records->each(function (Product $record) {
                                $record->update([
                                    'promotion' => false
                                ]);
                            });
                        })
                        ->successNotificationTitle('toggle completed')
                        ->failureNotificationTitle(function (int $successCount, int $totalCount): string {
                            return 'Failed to toggle';
                        }),
                ]),

            ]);
    }
}
