<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Exports extends Page
{
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-m-arrow-down-on-square-stack';
    protected string $view = 'filament.pages.reports';

}
