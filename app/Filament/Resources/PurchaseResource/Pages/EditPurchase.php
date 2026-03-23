<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use App\Models\Wallet;
use App\Services\PurchaseService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPurchase extends EditRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pay')
                ->label('Marcar como pagada')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'pending')
                ->form([
                    Forms\Components\Select::make('wallet_id')
                        ->label('Billetera')
                        ->options(fn (): array => Wallet::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->required()
                        ->searchable(),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    app(PurchaseService::class)->pay($this->record, (int) $data['wallet_id']);

                    $this->refreshFormData([
                        'status',
                        'wallet_id',
                    ]);

                    Notification::make()
                        ->title('Compra marcada como pagada.')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('void')
                ->label('Anular')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status !== 'voided')
                ->action(function (): void {
                    app(PurchaseService::class)->void($this->record, auth()->id());

                    $this->refreshFormData([
                        'status',
                        'notes',
                    ]);

                    Notification::make()
                        ->title('Compra anulada.')
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
