<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WhatsAppMessageLogResource\Pages;
use App\Models\WhatsAppMessageLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WhatsAppMessageLogResource extends Resource
{
    protected static ?string $model = WhatsAppMessageLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Administración';

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
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
