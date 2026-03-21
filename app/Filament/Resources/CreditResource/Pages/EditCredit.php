<?php

namespace App\Filament\Resources\CreditResource\Pages;

use App\Filament\Resources\CreditResource;
use App\Filament\Resources\PaymentResource;
use App\Models\Installment;
use App\Services\PaymentService;
use Carbon\CarbonImmutable;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCredit extends EditRecord
{
    protected static string $resource = CreditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('quick_pay')
                ->label('Pago rápido')
                ->icon('heroicon-s-bolt')
                ->form([
                    Forms\Components\DatePicker::make('paid_on')
                        ->label('Fecha')
                        ->default(now())
                        ->required(),
                    Forms\Components\Select::make('method')
                        ->label('Método')
                        ->options([
                            'cash' => 'Efectivo',
                            'transfer' => 'Transferencia',
                            'card' => 'Tarjeta',
                            'yape' => 'Yape/Plin',
                        ])
                        ->default('cash')
                        ->required(),
                    Forms\Components\TextInput::make('amount')
                        ->label('Monto sugerido')
                        ->numeric()
                        ->inputMode('decimal')
                        ->required()
                        ->default(function (): float {
                            $installment = Installment::query()
                                ->where('credit_id', $this->getRecord()->id)
                                ->where('status', '!=', 'paid')
                                ->orderBy('due_date')
                                ->orderBy('number')
                                ->first();

                            if (! $installment) {
                                return 0;
                            }

                            return max(0, (float) $installment->total_amount - (float) $installment->paid_amount);
                        }),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notas')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $credit = $this->getRecord();

                    $installment = Installment::query()
                        ->where('credit_id', $credit->id)
                        ->where('status', '!=', 'paid')
                        ->orderBy('due_date')
                        ->orderBy('number')
                        ->first();

                    if (! $installment) {
                        Notification::make()
                            ->title('No hay cuotas pendientes para cobrar.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $amount = (string) ($data['amount'] ?? '0');

                    app(PaymentService::class)->recordPayment(
                        credit: $credit,
                        amount: $amount,
                        paidOn: CarbonImmutable::parse((string) $data['paid_on']),
                        installment: $installment,
                        meta: [
                            'method' => $data['method'] ?? 'cash',
                            'notes' => $data['notes'] ?? null,
                            'status' => 'posted',
                            'created_by' => auth()->id(),
                        ],
                    );

                    Notification::make()
                        ->title('Pago registrado.')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('register_payment')
                ->label('Registrar pago')
                ->icon('heroicon-o-credit-card')
                ->url(fn (): string => PaymentResource::getUrl('create', ['credit_id' => $this->getRecord()->id]))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }
}
