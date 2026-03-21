<?php

namespace App\Filament\Resources\WhatsAppMessageLogResource\Pages;

use App\Filament\Resources\WhatsAppMessageLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppMessageLogs extends ListRecords
{
    protected static string $resource = WhatsAppMessageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
