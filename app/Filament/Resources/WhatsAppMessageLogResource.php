<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WhatsAppMessageLogResource\Pages;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\WhatsAppMessageLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WhatsAppMessageLogResource extends Resource
{
    protected static ?string $model = WhatsAppMessageLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?string $modelLabel = 'Log de WhatsApp';

    protected static ?string $pluralModelLabel = 'Logs de WhatsApp';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('event')->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('to')->disabled()->dehydrated(false),
                        Forms\Components\Textarea::make('message')->disabled()->dehydrated(false)->rows(6),
                        Forms\Components\TextInput::make('status')->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('attempts')->disabled()->dehydrated(false),
                        Forms\Components\Textarea::make('last_error')->disabled()->dehydrated(false)->rows(4),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event')
                    ->label('Evento')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('to')
                    ->label('Destino')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('attempts')
                    ->label('Intentos')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Enviado')
                    ->dateTime()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_error')
                    ->label('Error')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
            ])
            ->actions([
                Tables\Actions\Action::make('retry')
                    ->label('Reintentar')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (WhatsAppMessageLog $record): bool => $record->status === 'failed')
                    ->action(function (WhatsAppMessageLog $record): void {
                        $record->status = 'queued';
                        $record->last_error = null;
                        $record->sent_at = null;
                        $record->save();

                        SendWhatsAppMessageJob::dispatch($record->id);

                        Notification::make()
                            ->title('Reintento encolado.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('resend')
                    ->label('Reenviar')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (WhatsAppMessageLog $record): bool => in_array($record->status, ['sent', 'failed'], true))
                    ->action(function (WhatsAppMessageLog $record): void {
                        $new = WhatsAppMessageLog::query()->create([
                            'channel' => $record->channel,
                            'event' => $record->event,
                            'customer_id' => $record->customer_id,
                            'credit_id' => $record->credit_id,
                            'installment_id' => $record->installment_id,
                            'payment_id' => $record->payment_id,
                            'to' => $record->to,
                            'message' => $record->message,
                            'status' => 'queued',
                            'fingerprint' => null,
                            'context' => array_merge((array) ($record->context ?? []), [
                                'resend_of' => $record->id,
                            ]),
                            'attempts' => 0,
                            'last_error' => null,
                            'sent_at' => null,
                            'provider_payload' => null,
                            'provider_response' => null,
                        ]);

                        SendWhatsAppMessageJob::dispatch($new->id);

                        Notification::make()
                            ->title('Reenvío encolado.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatsAppMessageLogs::route('/'),
            'edit' => Pages\EditWhatsAppMessageLog::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
