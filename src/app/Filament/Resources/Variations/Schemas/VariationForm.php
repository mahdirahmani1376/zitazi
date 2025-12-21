<?php

namespace App\Filament\Resources\Variations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VariationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('product_id'),
                TextInput::make('price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('url')
                    ->url(),
                TextInput::make('stock')
                    ->numeric(),
                TextInput::make('rial_price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('own_id')
                    ->numeric(),
                Toggle::make('is_deleted'),
            ]);
    }
}
