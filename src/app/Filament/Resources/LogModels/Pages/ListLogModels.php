<?php

namespace App\Filament\Resources\LogModels\Pages;

use App\Filament\Resources\LogModels\LogModelResource;
use Filament\Resources\Pages\ListRecords;

class ListLogModels extends ListRecords
{
    protected static string $resource = LogModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
