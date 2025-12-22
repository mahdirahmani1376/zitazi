<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('own_id')
                    ->required(),
                TextInput::make('trendyol_source'),
                TextInput::make('base_source')
                    ->required()
                    ->default('zitazi'),
                TextInput::make('digikala_source'),
                TextInput::make('sazkala_source'),
                Textarea::make('torob_source')
                    ->columnSpanFull(),
                TextInput::make('torob_id'),
                TextInput::make('markup')
                    ->numeric(),
                TextInput::make('category'),
                TextInput::make('brand'),
                TextInput::make('owner'),
                TextInput::make('product_name'),
                TextInput::make('decathlon_url')
                    ->url(),
                TextInput::make('eth_source'),
                TextInput::make('decathlon_id'),
                TextInput::make('elele_source'),
                TextInput::make('matilda_source'),
                Toggle::make('promotion'),
                TextInput::make('amazon_source'),
            ]);
    }
}
