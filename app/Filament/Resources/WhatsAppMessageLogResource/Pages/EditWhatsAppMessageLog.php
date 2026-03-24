<?php

namespace App\Filament\Resources\WhatsAppMessageLogResource\Pages;

use App\Filament\Resources\WhatsAppMessageLogResource;
use Filament\Resources\Pages\EditRecord;

class EditWhatsAppMessageLog extends EditRecord
{
    protected static string $resource = WhatsAppMessageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
