<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('own_id'),
                TextEntry::make('trendyol_source')
                    ->placeholder('-'),
                TextEntry::make('base_source'),
                TextEntry::make('digikala_source')
                    ->placeholder('-'),
                TextEntry::make('sazkala_source')
                    ->placeholder('-'),
                TextEntry::make('torob_source')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('torob_id')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('min_price')
                    ->money()
                    ->placeholder('-'),
                TextEntry::make('rival_min_price')
                    ->money()
                    ->placeholder('-'),
                TextEntry::make('markup')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('category')
                    ->placeholder('-'),
                TextEntry::make('brand')
                    ->placeholder('-'),
                TextEntry::make('owner')
                    ->placeholder('-'),
                TextEntry::make('product_name')
                    ->placeholder('-'),
                TextEntry::make('decathlon_url')
                    ->placeholder('-'),
                TextEntry::make('eth_source')
                    ->placeholder('-'),
                TextEntry::make('decathlon_id')
                    ->placeholder('-'),
                TextEntry::make('elele_source')
                    ->placeholder('-'),
                TextEntry::make('matilda_source')
                    ->placeholder('-'),
                IconEntry::make('promotion')
                    ->boolean()
                    ->placeholder('-'),
                TextEntry::make('amazon_source')
                    ->placeholder('-'),
            ]);
    }
}
