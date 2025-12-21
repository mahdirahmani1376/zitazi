<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                    ->searchable(),
                TextColumn::make('base_source')
                    ->searchable(),
                TextColumn::make('digikala_source')
                    ->limit(10)
                    ->tooltip(fn($record) => $record->digikala_source)
                    ->searchable(),
                TextColumn::make('sazkala_source')
                    ->limit(10)
                    ->tooltip(fn($record) => $record->sazkala_source)
                    ->searchable(),
                TextColumn::make('torob_id')
                    ->limit(10)
                    ->tooltip(fn($record) => $record->torob_id)
                    ->searchable(),
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
                    ->searchable(),
                TextColumn::make('brand')
                    ->searchable(),
                TextColumn::make('owner')
                    ->searchable(),
                TextColumn::make('product_name')
                    ->searchable(),
                TextColumn::make('decathlon_url')
                    ->limit(10)
                    ->tooltip(fn($record) => $record->decathlon_url)
                    ->searchable(),
                TextColumn::make('eth_source')
                    ->limit(10)
                    ->tooltip(fn($record) => $record->eth_source)
                    ->searchable(),
                TextColumn::make('decathlon_id')
                    ->searchable(),
                TextColumn::make('elele_source')
                    ->limit(10)
                    ->tooltip(fn($record) => $record->elele_source)
                    ->searchable(),
                TextColumn::make('matilda_source')
                    ->limit(10)
                    ->tooltip(fn($record) => $record->matilda_source)
                    ->searchable(),
                IconColumn::make('promotion')
                    ->boolean(),
                TextColumn::make('amazon_source')
                    ->limit(10)
                    ->tooltip(fn($record) => $record->amazon_source)
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
