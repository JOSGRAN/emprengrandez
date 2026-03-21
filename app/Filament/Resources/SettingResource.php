<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingResource\Pages;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?string $modelLabel = 'Configuración';

    protected static ?string $pluralModelLabel = 'Configuración';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->label('Key')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options([
                                'string' => 'Texto',
                                'int' => 'Número',
                                'bool' => 'Sí/No',
                            ])
                            ->default('string')
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('value_string')
                            ->label('Valor')
                            ->visible(fn (Forms\Get $get): bool => $get('type') === 'string')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('value_int')
                            ->label('Valor')
                            ->visible(fn (Forms\Get $get): bool => $get('type') === 'int')
                            ->numeric()
                            ->inputMode('numeric'),
                        Forms\Components\Toggle::make('value_bool')
                            ->label('Valor')
                            ->visible(fn (Forms\Get $get): bool => $get('type') === 'bool'),
                        Forms\Components\TextInput::make('description')
                            ->label('Descripción')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label('Key')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('value')
                    ->label('Valor')
                    ->formatStateUsing(function ($state): string {
                        if (is_bool($state)) {
                            return $state ? 'Sí' : 'No';
                        }

                        if (is_array($state)) {
                            return json_encode($state, JSON_UNESCAPED_UNICODE) ?: '';
                        }

                        return (string) $state;
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettings::route('/'),
            'create' => Pages\CreateSetting::route('/create'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }
}
