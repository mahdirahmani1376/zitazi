<?php

namespace App\Filament\Resources\LogModels\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LogModelInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('product_id'),
                TextEntry::make('variation_id'),
                TextEntry::make('message'),
                TextEntry::make('data')
                    ->label('Raw Data')
                    ->state(fn ($record) => json_encode($record->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
                //                KeyValueEntry::make('data.body')->label('Update Body'),
                //                KeyValueEntry::make('data.response')->label('Response Body'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
