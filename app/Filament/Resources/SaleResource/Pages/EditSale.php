<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Services\SaleService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSale extends EditRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('void')
                ->label('Anular')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === 'posted')
                ->action(function (): void {
                    app(SaleService::class)->voidSale($this->record, auth()->id());

                    $this->refreshFormData([
                        'status',
                        'notes',
                    ]);

                    Notification::make()
                        ->title('Venta anulada.')
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
