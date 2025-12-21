<?php

namespace App\Filament\Resources\Variations\Pages;

use App\Filament\Resources\Variations\VariationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVariations extends ListRecords
{
    protected static string $resource = VariationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
