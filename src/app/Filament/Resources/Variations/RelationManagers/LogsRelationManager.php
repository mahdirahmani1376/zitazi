<?php

namespace App\Filament\Resources\Variations\RelationManagers;

use App\Filament\Resources\LogModels\LogModelResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $relatedResource = LogModelResource::class;
    protected $listeners = [
        'refreshLogs' => '$refresh',
    ];

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
            ]);
    }
}
