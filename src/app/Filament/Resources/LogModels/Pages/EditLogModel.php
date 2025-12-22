<?php

namespace App\Filament\Resources\LogModels\Pages;

use App\Filament\Resources\LogModels\LogModelResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLogModel extends EditRecord
{
    protected static string $resource = LogModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
