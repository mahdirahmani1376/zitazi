<?php

namespace App\Filament\Pages;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class Imports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationLabel = 'Import Variations';
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-m-arrow-up-on-square';
    protected string $view = 'filament.pages.import-variations';
}
