<?php

namespace App\Filament\Resources\CreditResource\RelationManagers;

use App\Filament\Resources\PaymentResource;
use App\Services\NotificationService;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('number')
            ->recordTitleAttribute('number')
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Vence')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Pagado')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('remaining')
                    ->label('Saldo')
                    ->state(fn ($record): float => max(0, (float) $record->total_amount - (float) $record->paid_amount))
                    ->money('PEN'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->icon(fn (string $state): string => match ($state) {
                        'paid' => 'heroicon-s-check-circle',
                        'overdue' => 'heroicon-s-exclamation-triangle',
                        'pending' => 'heroicon-s-clock',
                        default => 'heroicon-s-minus-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'overdue' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    })
                    ->extraAttributes(fn ($record): array => $record->status === 'overdue' ? ['class' => 'animate-pulse'] : []),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Pagado el')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('pay')
                    ->label('Pagar')
                    ->icon('heroicon-o-credit-card')
                    ->visible(fn ($record): bool => $record->status !== 'paid')
                    ->url(fn ($record): string => PaymentResource::getUrl('create', [
                        'credit_id' => $this->getOwnerRecord()->id,
                        'installment_id' => $record->id,
                        'amount' => max(0, (float) $record->total_amount - (float) $record->paid_amount),
                    ])),
                Tables\Actions\Action::make('whatsapp_reminder')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $log = app(NotificationService::class)->queueInstallmentManualReminder($record);

                        if (! $log) {
                            Notification::make()
                                ->title('No se pudo encolar el mensaje (cliente sin WhatsApp o notificaciones deshabilitadas).')
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Recordatorio encolado.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('history')
                    ->label('Historial')
                    ->icon('heroicon-o-clock')
                    ->url(fn ($record): string => PaymentResource::getUrl('index', [
                        'tableFilters' => [
                            'installment_id' => [
                                'value' => $record->id,
                            ],
                        ],
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->headerActions([])
            ->bulkActions([])
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]));
    }
}
