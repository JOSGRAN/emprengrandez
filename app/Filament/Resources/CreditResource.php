<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreditResource\Pages;
use App\Filament\Resources\CreditResource\RelationManagers\InstallmentsRelationManager;
use App\Filament\Resources\CreditResource\RelationManagers\PaymentsRelationManager;
use App\Models\Credit;
use App\Models\Installment;
use App\Services\CreditService;
use App\Services\PaymentService;
use Carbon\CarbonImmutable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CreditResource extends Resource
{
    protected static ?string $model = Credit::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finanzas';

    public static function form(Form $form): Form
    {
        $updatePreview = function (Forms\Set $set, Forms\Get $get): void {
            $principalAmount = (string) ($get('principal_amount') ?? '');
            $installmentsCount = (int) ($get('installments_count') ?? 0);
            $frequency = (string) ($get('frequency') ?? 'monthly');
            $interestType = (string) ($get('interest_type') ?? 'none');
            $interestRate = (string) ($get('interest_rate') ?? 0);
            $calculationMethod = (string) ($get('calculation_method') ?? 'direct');
            $startDate = $get('start_date');

            if ($principalAmount === '' || $installmentsCount < 1 || blank($startDate)) {
                $set('schedule_preview', []);

                return;
            }

            if ($interestType === 'none') {
                $interestRate = '0';
                $calculationMethod = 'direct';
            }

            try {
                $schedule = app(CreditService::class)->generateSchedule(
                    principalAmount: $principalAmount,
                    installmentsCount: $installmentsCount,
                    frequency: $frequency,
                    interestType: $interestType,
                    interestRate: $interestRate,
                    calculationMethod: $calculationMethod,
                    startDate: CarbonImmutable::parse($startDate),
                );
            } catch (\Throwable) {
                $schedule = [];
            }

            $set('schedule_preview', $schedule);
        };

        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        Forms\Components\Select::make('customer_id')
                            ->label('Cliente')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Fecha de inicio')
                            ->default(now())
                            ->required()
                            ->live()
                            ->afterStateUpdated($updatePreview),
                        Forms\Components\TextInput::make('principal_amount')
                            ->label('Monto')
                            ->numeric()
                            ->inputMode('decimal')
                            ->required()
                            ->live()
                            ->afterStateUpdated($updatePreview),
                        Forms\Components\TextInput::make('installments_count')
                            ->label('N° cuotas')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->live()
                            ->afterStateUpdated($updatePreview),
                        Forms\Components\Select::make('frequency')
                            ->label('Frecuencia')
                            ->options([
                                'weekly' => 'Semanal',
                                'biweekly' => 'Quincenal',
                                'monthly' => 'Mensual',
                            ])
                            ->default('monthly')
                            ->required()
                            ->live()
                            ->afterStateUpdated($updatePreview),
                        Forms\Components\Select::make('interest_type')
                            ->label('Tipo de interés')
                            ->options([
                                'none' => 'Sin interés',
                                'simple' => 'Interés simple',
                                'compound' => 'Interés compuesto',
                            ])
                            ->default('none')
                            ->required()
                            ->live()
                            ->afterStateUpdated($updatePreview),
                        Forms\Components\TextInput::make('interest_rate')
                            ->label('Tasa (por periodo, ej: 0.05 = 5%)')
                            ->numeric()
                            ->inputMode('decimal')
                            ->default(0)
                            ->visible(fn (Forms\Get $get) => $get('interest_type') !== 'none')
                            ->live()
                            ->afterStateUpdated($updatePreview),
                        Forms\Components\Select::make('calculation_method')
                            ->label('Cálculo')
                            ->options([
                                'direct' => 'División directa',
                                'french' => 'Cuotas fijas (francés)',
                                'german' => 'Cuotas decrecientes (alemán)',
                            ])
                            ->default('direct')
                            ->required()
                            ->visible(fn (Forms\Get $get) => $get('interest_type') !== 'none')
                            ->live()
                            ->afterStateUpdated($updatePreview),
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'active' => 'Activo',
                                'closed' => 'Cerrado',
                                'cancelled' => 'Cancelado',
                            ])
                            ->default('active')
                            ->required(),
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        Forms\Components\TextInput::make('balance')
                            ->label('Saldo')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Simulación')
                    ->visibleOn('create')
                    ->schema([
                        Forms\Components\Placeholder::make('schedule_summary')
                            ->label('Resumen')
                            ->content(function (Forms\Get $get): string {
                                $items = $get('schedule_preview') ?? [];
                                if (! is_array($items) || count($items) === 0) {
                                    return 'Completa los datos para ver la simulación.';
                                }

                                $total = array_sum(array_map(fn ($r) => (float) ($r['total_amount'] ?? 0), $items));

                                return 'Total estimado: S/ '.number_format($total, 2, '.', ',');
                            }),
                        Forms\Components\Repeater::make('schedule_preview')
                            ->label('Cronograma')
                            ->dehydrated(false)
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable(false)
                            ->schema([
                                Forms\Components\TextInput::make('number')->label('#')->disabled(),
                                Forms\Components\TextInput::make('due_date')->label('Vence')->disabled(),
                                Forms\Components\TextInput::make('principal_amount')->label('Capital')->disabled(),
                                Forms\Components\TextInput::make('interest_amount')->label('Interés')->disabled(),
                                Forms\Components\TextInput::make('total_amount')->label('Total')->disabled(),
                            ])
                            ->columns(5),
                    ]),
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
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('principal_amount')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance')
                    ->label('Saldo')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'warning',
                        'closed' => 'success',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('quick_pay')
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
                            ->default(function (Credit $record): float {
                                $installment = Installment::query()
                                    ->where('credit_id', $record->id)
                                    ->where('status', '!=', 'paid')
                                    ->orderBy('due_date')
                                    ->orderBy('number')
                                    ->first();

                                if (! $installment) {
                                    return 0;
                                }

                                return max(0, (float) $installment->total_amount - (float) $installment->paid_amount);
                            }),
                    ])
                    ->action(function (Credit $record, array $data): void {
                        $installment = Installment::query()
                            ->where('credit_id', $record->id)
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

                        app(PaymentService::class)->recordPayment(
                            credit: $record,
                            amount: (string) ($data['amount'] ?? '0'),
                            paidOn: CarbonImmutable::parse((string) $data['paid_on']),
                            installment: $installment,
                            meta: [
                                'method' => $data['method'] ?? 'cash',
                                'status' => 'posted',
                                'created_by' => auth()->id(),
                            ],
                        );

                        Notification::make()
                            ->title('Pago registrado.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('register_payment')
                    ->label('Registrar pago')
                    ->icon('heroicon-o-credit-card')
                    ->url(fn (Credit $record): string => PaymentResource::getUrl('create', ['credit_id' => $record->id]))
                    ->openUrlInNewTab(),
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
            ->with(['customer'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRelations(): array
    {
        return [
            InstallmentsRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCredits::route('/'),
            'create' => Pages\CreateCredit::route('/create'),
            'edit' => Pages\EditCredit::route('/{record}/edit'),
        ];
    }
}
