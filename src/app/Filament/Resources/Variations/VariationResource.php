<?php

namespace App\Filament\Resources\Variations;

use App\Filament\Resources\Products\RelationManagers\LogsRelationManager;
use App\Filament\Resources\Variations\Pages\CreateVariation;
use App\Filament\Resources\Variations\Pages\EditVariation;
use App\Filament\Resources\Variations\Pages\ListVariations;
use App\Filament\Resources\Variations\Pages\ViewVariation;
use App\Filament\Resources\Variations\RelationManagers\ProductRelationManager;
use App\Filament\Resources\Variations\Schemas\VariationForm;
use App\Filament\Resources\Variations\Schemas\VariationInfolist;
use App\Filament\Resources\Variations\Tables\VariationsTable;
use App\Models\Variation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VariationResource extends Resource
{
    protected static ?string $model = Variation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return VariationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VariationsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VariationInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            ProductRelationManager::class,
            LogsRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVariations::route('/'),
            'create' => CreateVariation::route('/create'),
            'edit' => EditVariation::route('/{record}/edit'),
            'view' => ViewVariation::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['own_id', 'product.own_id', 'product.product_name'];
    }
}
