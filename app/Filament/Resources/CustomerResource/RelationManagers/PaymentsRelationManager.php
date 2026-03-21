<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Filament\Resources\PaymentResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Código')->searchable(),
                Tables\Columns\TextColumn::make('paid_on')->label('Fecha')->date()->sortable(),
                Tables\Columns\TextColumn::make('credit.code')->label('Crédito')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('installment.number')->label('Cuota')->toggleable(),
                Tables\Columns\TextColumn::make('amount')->label('Monto')->money('PEN')->sortable(),
                Tables\Columns\TextColumn::make('method')->label('Método')->badge()->toggleable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_payment')
                    ->label('Registrar pago')
                    ->icon('heroicon-o-credit-card')
                    ->url(fn (): string => PaymentResource::getUrl('create'))
                    ->openUrlInNewTab(),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Abrir')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record): string => PaymentResource::getUrl('edit', ['record' => $record]))
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
