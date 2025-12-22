<?php

namespace App\Filament\Resources\LogModels\Pages;

use App\Filament\Resources\LogModels\LogModelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLogModel extends CreateRecord
{
    protected static string $resource = LogModelResource::class;
}
