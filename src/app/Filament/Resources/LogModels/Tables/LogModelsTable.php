<?php

namespace App\Filament\Resources\LogModels\Tables;

use App\Models\LogModel;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LogModelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product_id')->searchable(),
                TextColumn::make('variation_id')->searchable(),
                TextColumn::make('message'),
                TextColumn::make('data.stock_status')->label('Stock Status'),
                TextColumn::make('data.regular_price')->label('Regular Price'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('message')
                    ->multiple()
                    ->options(
                        array_combine(
                            LogModel::query()->whereNotNull('message')->distinct('message')->pluck('message')->all(),
                            LogModel::query()->whereNotNull('message')->distinct('message')->pluck('message')->all()
                        ),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
