<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers\CreditsRelationManager;
use App\Filament\Resources\CustomerResource\RelationManagers\PaymentsRelationManager;
use App\Models\Credit;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\Payment;
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
use Illuminate\Support\HtmlString;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clientes';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Resumen financiero')
                    ->visibleOn('edit')
                    ->schema([
                        Forms\Components\Placeholder::make('total_debt')
                            ->label('Total deuda')
                            ->content(function (?Customer $record): string {
                                if (! $record) {
                                    return '-';
                                }

                                $debt = (float) Credit::query()
                                    ->where('customer_id', $record->id)
                                    ->where('status', 'active')
                                    ->sum('balance');

                                return 'S/ '.number_format($debt, 2, '.', ',');
                            }),
                        Forms\Components\Placeholder::make('total_paid')
                            ->label('Total pagado')
                            ->content(function (?Customer $record): string {
                                if (! $record) {
                                    return '-';
                                }

                                $paid = (float) Payment::query()
                                    ->where('customer_id', $record->id)
                                    ->where('status', 'posted')
                                    ->sum('amount');

                                return 'S/ '.number_format($paid, 2, '.', ',');
                            }),
                        Forms\Components\Placeholder::make('overdue_installments')
                            ->label('Cuotas vencidas')
                            ->content(function (?Customer $record): string {
                                if (! $record) {
                                    return '-';
                                }

                                $count = (int) Installment::query()
                                    ->whereHas('credit', fn ($q) => $q->where('customer_id', $record->id))
                                    ->where('status', 'overdue')
                                    ->count();

                                return number_format($count, 0, '.', ',');
                            }),
                        Forms\Components\Placeholder::make('last_payment')
                            ->label('Último pago')
                            ->content(function (?Customer $record): string {
                                if (! $record) {
                                    return '-';
                                }

                                $payment = Payment::query()
                                    ->where('customer_id', $record->id)
                                    ->where('status', 'posted')
                                    ->latest('paid_on')
                                    ->first();

                                if (! $payment) {
                                    return '-';
                                }

                                return $payment->paid_on->format('Y-m-d').' — S/ '.number_format((float) $payment->amount, 2, '.', ',');
                            }),
                    ])
                    ->columns(4),
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('document_type')
                            ->label('Tipo documento')
                            ->options([
                                'dni' => 'DNI',
                                'ruc' => 'RUC',
                                'ce' => 'CE',
                            ])
                            ->searchable(),
                        Forms\Components\TextInput::make('document_number')
                            ->label('Nro documento')
                            ->maxLength(30),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->maxLength(30),
                        Forms\Components\TextInput::make('whatsapp')
                            ->label('WhatsApp')
                            ->maxLength(30),
                        Forms\Components\Textarea::make('address')
                            ->label('Dirección')
                            ->rows(2),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3),
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'active' => 'Activo',
                                'inactive' => 'Inactivo',
                            ])
                            ->default('active')
                            ->required(),
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
                    ->toggleable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('document_number')
                    ->label('Documento')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('whatsapp')
                    ->label('WhatsApp')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('create_credit')
                    ->label('Crear crédito')
                    ->icon('heroicon-o-banknotes')
                    ->url(fn (Customer $record): string => CreditResource::getUrl('create', ['customer_id' => $record->id]))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('register_payment')
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
                            ->default(function (Customer $record): float {
                                $installment = Installment::query()
                                    ->whereHas('credit', fn ($q) => $q->where('customer_id', $record->id)->where('status', 'active'))
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
                    ->action(function (Customer $record, array $data): void {
                        $installment = Installment::query()
                            ->whereHas('credit', fn ($q) => $q->where('customer_id', $record->id)->where('status', 'active'))
                            ->where('status', '!=', 'paid')
                            ->orderBy('due_date')
                            ->orderBy('number')
                            ->first();

                        if (! $installment) {
                            Notification::make()
                                ->title('El cliente no tiene cuotas pendientes.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $credit = $installment->credit;
                        if (! $credit) {
                            Notification::make()
                                ->title('No se encontró el crédito asociado.')
                                ->danger()
                                ->send();

                            return;
                        }

                        app(PaymentService::class)->recordPayment(
                            credit: $credit,
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
                Tables\Actions\Action::make('view_debt')
                    ->label('Ver deuda')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (Customer $record): string => 'Deuda: '.$record->name)
                    ->modalContent(function (Customer $record): HtmlString {
                        $activeCredits = Credit::query()
                            ->where('customer_id', $record->id)
                            ->where('status', 'active')
                            ->get(['id', 'balance']);

                        $totalDebt = (float) $activeCredits->sum('balance');
                        $activeCreditsCount = (int) $activeCredits->count();

                        $overdue = (float) Installment::query()
                            ->whereHas('credit', fn ($q) => $q->where('customer_id', $record->id))
                            ->where('status', 'overdue')
                            ->get()
                            ->sum(fn ($i) => max(0, (float) $i->total_amount - (float) $i->paid_amount));

                        $html = '<div class="space-y-2">';
                        $html .= '<div><strong>Total pendiente:</strong> S/ '.number_format($totalDebt, 2, '.', ',').'</div>';
                        $html .= '<div><strong>Créditos activos:</strong> '.number_format($activeCreditsCount, 0, '.', ',').'</div>';
                        $html .= '<div><strong>En mora:</strong> S/ '.number_format($overdue, 2, '.', ',').'</div>';
                        $html .= '</div>';

                        return new HtmlString($html);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
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
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CreditsRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
