<?php

namespace App\Filament\Resources\Products\Tables;

use App\Actions\Filament\SyncAndUpdateProductButtonAction;
use App\Enums\SyncStatusEnum;
use App\Exports\FillamentProductExport;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Variations\RelationManagers\ProductRelationManager;
use App\Jobs\SendScrapeMessageJob;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                TextColumn::make('sync_status')
                    ->label('Sync Status')
                    ->state(function (Product $record, ListProducts|ProductRelationManager $livewire) {
                        return $record->sync_status;
                    })
                    ->formatStateUsing(fn(SyncStatusEnum $state) => $state->label())
                    ->badge()
                    ->color(fn(SyncStatusEnum $state) => $state->getBadgeColorForState()),
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
                SelectFilter::make('source')
                    ->preload()
                    ->options([
                        Product::SOURCE_TRENDYOL => Product::SOURCE_TRENDYOL,
                        Product::SOURCE_DECATHLON => Product::SOURCE_DECATHLON
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(data_get($data, 'value') === Product::SOURCE_DECATHLON,
                                function (Builder $query, string $value) {
                                    return $query
                                        ->whereNotNull('decathlon_url')
                                        ->orWhereRaw('trim(decathlon_url) != ""');
                                })
                            ->when(data_get($data, 'value') === Product::SOURCE_TRENDYOL,
                                function (Builder $query, string $value) {
                                    return $query
                                        ->whereNotNull('trendyol_source')
                                        ->orWhereRaw('trim(trendyol_source) != ""');
                                }
                            );
                    }),
                Filter::make('created_at')
                    ->label('Created at')
                    ->schema([
                        DatePicker::make('from')
                            ->label('created_at from'),

                        DatePicker::make('until')
                            ->label('created_at until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date)
                            );
                    }),
                Filter::make('updated_at')
                    ->label('Updated at')
                    ->schema([
                        DatePicker::make('from')
                            ->label('updated_at From'),

                        DatePicker::make('until')
                            ->label('updated_at Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('updated_at', '>=', $date)
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('updated_at', '<=', $date)
                            );
                    }),
                SelectFilter::make('category')
                    ->multiple()
                    ->preload()
                    ->options(
                        Product::query()
                            ->whereNotNull('category')
                            ->where('category', '!=', '')
                            ->distinct('category')
                            ->orderBy('category')
                            ->pluck('category', 'category')
                            ->toArray()
                    ),
                SelectFilter::make('brand')
                    ->multiple()
                    ->preload()
                    ->options(
                        Product::query()
                            ->whereNotNull('brand')
                            ->where('brand', '!=', '')
                            ->distinct('brand')
                            ->orderBy('brand')
                            ->pluck('brand', 'brand')
                            ->toArray()
                    ),
                SelectFilter::make('owner')
                    ->preload()
                    ->multiple()
                    ->options(
                        Product::query()
                            ->whereNotNull('owner')
                            ->where('owner', '!=', '')
                            ->distinct('owner')
                            ->orderBy('owner')
                            ->pluck('owner', 'owner')
                            ->toArray()
                    ),
                SelectFilter::make('base_source')
                    ->multiple()
                    ->options([
                        Product::SATRE => Product::SATRE,
                        Product::ZITAZI => Product::ZITAZI,
                    ]),
                TernaryFilter::make('promotion'),
                SelectFilter::make('sync_status')
                    ->multiple()
                    ->options(SyncStatusEnum::getValues()),
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
                                $jobs[] = new SendScrapeMessageJob($record);
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
