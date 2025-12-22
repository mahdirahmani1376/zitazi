<?php

namespace App\Filament\Resources\LogModels;

use App\Filament\Resources\LogModels\Pages\ListLogModels;
use App\Filament\Resources\LogModels\Pages\ViewLogModel;
use App\Filament\Resources\LogModels\RelationManagers\ProductRelationManager;
use App\Filament\Resources\LogModels\RelationManagers\VariationRelationManager;
use App\Filament\Resources\LogModels\Schemas\LogModelForm;
use App\Filament\Resources\LogModels\Schemas\LogModelInfolist;
use App\Filament\Resources\LogModels\Tables\LogModelsTable;
use App\Models\LogModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LogModelResource extends Resource
{
    protected static ?string $model = LogModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'message';

    public static function form(Schema $schema): Schema
    {
        return LogModelForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LogModelInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LogModelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            VariationRelationManager::class,
            ProductRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLogModels::route('/'),
            'view' => ViewLogModel::route('/{record}'),
        ];
    }
}
