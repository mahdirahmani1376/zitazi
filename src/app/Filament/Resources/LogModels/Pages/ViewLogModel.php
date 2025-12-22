<?php

namespace App\Filament\Resources\LogModels\Pages;

use App\Filament\Resources\LogModels\LogModelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLogModel extends ViewRecord
{
    protected static string $resource = LogModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
