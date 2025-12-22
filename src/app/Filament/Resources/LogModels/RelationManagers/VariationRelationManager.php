<?php

namespace App\Filament\Resources\LogModels\RelationManagers;

use App\Filament\Resources\Variations\VariationResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class VariationRelationManager extends RelationManager
{
    protected static string $relationship = 'variation';

    protected static ?string $relatedResource = VariationResource::class;


    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
            ]);
    }
}
