<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Credit;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Wallet;
use App\Services\WalletService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Finanzas';

    protected static ?string $modelLabel = 'Pago';

    protected static ?string $pluralModelLabel = 'Pagos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        Forms\Components\Select::make('credit_id')
                            ->label('Crédito')
                            ->relationship('credit', 'code')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Forms\Components\Placeholder::make('credit_balance')
                            ->label('Saldo del crédito')
                            ->content(function (Forms\Get $get): string {
                                $creditId = $get('credit_id');
                                if (! $creditId) {
                                    return '-';
                                }

                                $credit = Credit::query()->find($creditId);
                                if (! $credit) {
                                    return '-';
                                }

                                return 'S/ '.number_format((float) $credit->balance, 2, '.', ',');
                            }),
                        Forms\Components\Select::make('installment_id')
                            ->label('Cuota (opcional)')
                            ->options(function (Forms\Get $get): array {
                                $creditId = $get('credit_id');
                                if (! $creditId) {
                                    return [];
                                }

                                return Installment::query()
                                    ->where('credit_id', $creditId)
                                    ->where('status', '!=', 'paid')
                                    ->orderBy('number')
                                    ->get()
                                    ->mapWithKeys(function (Installment $i) {
                                        $label = sprintf(
                                            'Cuota #%d - Vence %s - Total S/ %s - Pagado S/ %s',
                                            $i->number,
                                            $i->due_date?->format('Y-m-d'),
                                            number_format((float) $i->total_amount, 2, '.', ''),
                                            number_format((float) $i->paid_amount, 2, '.', ''),
                                        );

                                        return [$i->id => $label];
                                    })
                                    ->all();
                            })
                            ->searchable(),
                        Forms\Components\Placeholder::make('installment_remaining')
                            ->label('Saldo de la cuota')
                            ->content(function (Forms\Get $get): string {
                                $installmentId = $get('installment_id');
                                if (! $installmentId) {
                                    return '-';
                                }

                                $installment = Installment::query()->find($installmentId);
                                if (! $installment) {
                                    return '-';
                                }

                                $remaining = max(0, (float) $installment->total_amount - (float) $installment->paid_amount);

                                return 'S/ '.number_format($remaining, 2, '.', ',');
                            }),
                        Forms\Components\DatePicker::make('paid_on')
                            ->label('Fecha de pago')
                            ->default(now())
                            ->required(),
                        Forms\Components\Select::make('wallet_id')
                            ->label('Billetera')
                            ->options(fn (): array => Wallet::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->default(fn (): ?int => app(WalletService::class)->getDefaultWalletId())
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Monto')
                            ->numeric()
                            ->inputMode('decimal')
                            ->required()
                            ->minValue(0.01)
                            ->maxValue(function (Forms\Get $get): ?float {
                                $creditId = $get('credit_id');
                                if (! $creditId) {
                                    return null;
                                }

                                $installmentId = $get('installment_id');
                                if ($installmentId) {
                                    $installment = Installment::query()->find($installmentId);
                                    if (! $installment) {
                                        return null;
                                    }

                                    return max(0, (float) $installment->total_amount - (float) $installment->paid_amount);
                                }

                                $credit = Credit::query()->find($creditId);
                                if (! $credit) {
                                    return null;
                                }

                                return max(0, (float) $credit->balance);
                            }),
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
                        Forms\Components\TextInput::make('reference')
                            ->label('Referencia')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('credit.code')
                    ->label('Crédito')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('installment.number')
                    ->label('Cuota')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_on')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('method')
                    ->label('Método')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('installment_id')
                    ->label('Cuota')
                    ->relationship('installment', 'number')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['customer', 'credit', 'installment'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
