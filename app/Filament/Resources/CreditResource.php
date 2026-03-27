<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreditResource\Pages;
use App\Filament\Resources\CreditResource\RelationManagers\InstallmentsRelationManager;
use App\Filament\Resources\CreditResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\CreditResource\RelationManagers\PaymentsRelationManager;
use App\Models\Credit;
use App\Models\CreditItem;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
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

    protected static ?string $modelLabel = 'Crédito';

    protected static ?string $pluralModelLabel = 'Créditos';

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete($record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        $updatePreview = function (Forms\Set $set, Forms\Get $get): void {
            $items = $get('items') ?? [];
            $itemsTotal = 0.0;
            if (is_array($items)) {
                foreach ($items as $item) {
                    $qty = (int) ($item['quantity'] ?? 0);
                    $price = (float) ($item['price'] ?? 0);
                    $itemsTotal += max(0, $qty) * max(0, $price);
                }
            }

            $principalAmount = $itemsTotal > 0
                ? number_format($itemsTotal, 2, '.', '')
                : (string) ($get('principal_amount') ?? '');

            $installmentsCount = (int) ($get('installments_count') ?? 0);
            $frequency = (string) ($get('frequency') ?? 'monthly');
            $interestType = (string) ($get('interest_type') ?? 'none');
            $interestRate = (string) ($get('interest_rate') ?? 0);
            $calculationMethod = (string) ($get('calculation_method') ?? 'direct');
            $startDate = $get('start_date');

            if ($itemsTotal > 0) {
                $set('principal_amount', $principalAmount);
            }

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
                        Forms\Components\Repeater::make('items')
                            ->label('Productos')
                            ->visibleOn('create')
                            ->minItems(1)
                            ->reorderable(false)
                            ->live()
                            ->afterStateUpdated($updatePreview)
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Producto')
                                    ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set) {
                                        $set('product_variant_id', null);
                                        $set('price', null);
                                        $set('quantity', 1);
                                    }),
                                Forms\Components\Select::make('product_variant_id')
                                    ->label('Variante')
                                    ->options(function (Forms\Get $get): array {
                                        $productId = (int) ($get('product_id') ?? 0);
                                        if ($productId <= 0) {
                                            return [];
                                        }

                                        return ProductVariant::query()
                                            ->where('product_id', $productId)
                                            ->orderBy('id')
                                            ->get(['id', 'size', 'color', 'stock', 'price'])
                                            ->mapWithKeys(function (ProductVariant $v) {
                                                $parts = array_filter([$v->size, $v->color]);
                                                $label = trim(implode(' / ', $parts));
                                                $label = $label !== '' ? $label : ('Variante #'.$v->id);
                                                $label .= ' — Stock: '.(int) $v->stock;
                                                if ($v->price !== null) {
                                                    $label .= ' — S/ '.number_format((float) $v->price, 2, '.', '');
                                                }

                                                return [$v->id => $label];
                                            })
                                            ->all();
                                    })
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) use ($updatePreview) {
                                        $variantId = (int) ($get('product_variant_id') ?? 0);
                                        if ($variantId <= 0) {
                                            return;
                                        }

                                        $variant = ProductVariant::query()->find($variantId);
                                        if (! $variant) {
                                            return;
                                        }

                                        $price = $variant->price;
                                        if ($price === null || (float) $price <= 0) {
                                            $productId = (int) ($get('product_id') ?? 0);
                                            if ($productId > 0) {
                                                $fallback = Product::query()->whereKey($productId)->value('price');
                                                if ($fallback !== null && (float) $fallback > 0) {
                                                    $price = $fallback;
                                                }
                                            }
                                        }

                                        $set('price', (string) ($price ?? 0));
                                        $set('quantity', max(1, (int) ($get('quantity') ?? 1)));
                                        $updatePreview($set, $get);
                                    }),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated($updatePreview)
                                    ->maxValue(function (Forms\Get $get): ?int {
                                        $variantId = (int) ($get('product_variant_id') ?? 0);
                                        if ($variantId <= 0) {
                                            return null;
                                        }

                                        $stock = ProductVariant::query()->whereKey($variantId)->value('stock');
                                        if ($stock === null) {
                                            return null;
                                        }

                                        return (int) $stock;
                                    }),
                                Forms\Components\TextInput::make('price')
                                    ->label('Precio')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->minValue(0.01)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated($updatePreview),
                                Forms\Components\Placeholder::make('line_total')
                                    ->label('Total')
                                    ->content(function (Forms\Get $get): string {
                                        $qty = (int) ($get('quantity') ?? 0);
                                        $price = (float) ($get('price') ?? 0);
                                        $total = max(0, $qty) * max(0, $price);

                                        return 'S/ '.number_format($total, 2, '.', ',');
                                    }),
                            ])
                            ->columns(5),
                        Forms\Components\TextInput::make('principal_amount')
                            ->label('Monto (calculado)')
                            ->numeric()
                            ->inputMode('decimal')
                            ->live()
                            ->afterStateUpdated($updatePreview)
                            ->disabled()
                            ->dehydrated(),
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
                            ->required()
                            ->visibleOn('create'),
                        Forms\Components\Placeholder::make('status_view')
                            ->label('Estado')
                            ->visibleOn('edit')
                            ->content(fn (?Credit $record): string => match ($record?->status) {
                                'active' => 'Activo',
                                'closed' => 'Cerrado',
                                'cancelled' => 'Cancelado',
                                default => (string) ($record?->status ?? '-'),
                            }),
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
                Tables\Actions\Action::make('cancel_credit')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo')
                            ->rows(3)
                            ->required(),
                    ])
                    ->visible(function (Credit $record): bool {
                        if ((string) ($record->status ?? '') === 'cancelled') {
                            return false;
                        }

                        return ! Payment::query()
                            ->where('credit_id', $record->id)
                            ->where('status', 'posted')
                            ->exists();
                    })
                    ->action(function (Credit $record, array $data): void {
                        try {
                            app(CreditService::class)->cancelCredit(
                                credit: $record,
                                reason: (string) ($data['reason'] ?? ''),
                                cancelledBy: auth()->id(),
                            );
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Crédito cancelado.')
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
            ItemsRelationManager::class,
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
