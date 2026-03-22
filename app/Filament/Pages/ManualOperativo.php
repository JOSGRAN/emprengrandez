<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ManualOperativo extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Ayuda';

    protected static ?string $navigationLabel = 'Manual operativo';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Manual operativo';

    protected static string $view = 'filament.pages.manual-operativo';
}
