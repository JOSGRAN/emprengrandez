<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Filament\Resources\CreditResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CreditsRelationManager extends RelationManager
{
    protected static string $relationship = 'credits';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Código')->searchable(),
                Tables\Columns\TextColumn::make('start_date')->label('Inicio')->date()->sortable(),
                Tables\Columns\TextColumn::make('total_amount')->label('Total')->money('PEN')->sortable(),
                Tables\Columns\TextColumn::make('balance')->label('Saldo')->money('PEN')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'warning',
                        'closed' => 'success',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_credit')
                    ->label('Crear crédito')
                    ->icon('heroicon-o-banknotes')
                    ->url(fn (): string => CreditResource::getUrl('create', ['customer_id' => $this->getOwnerRecord()->id]))
                    ->openUrlInNewTab(),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Abrir')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record): string => CreditResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]));
    }
}
