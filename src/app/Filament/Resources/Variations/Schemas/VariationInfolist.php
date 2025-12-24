<?php

namespace App\Filament\Resources\Variations\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class VariationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('own_id'),
                TextEntry::make('product_id'),
                TextEntry::make('url'),
                TextEntry::make('status'),
                TextEntry::make('is_deleted'),
                TextEntry::make('item_type'),
                TextEntry::make('price')
                    ->money()
                    ->placeholder('-'),
                TextEntry::make('stock')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('rial_price')
                    ->money()
                    ->placeholder('-'),
                TextEntry::make('price')
                    ->money()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
