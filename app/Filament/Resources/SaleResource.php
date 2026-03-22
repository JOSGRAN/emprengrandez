<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Filament\Resources\SaleResource\RelationManagers\ItemsRelationManager;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Wallet;
use App\Services\WalletService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?string $modelLabel = 'Venta';

    protected static ?string $pluralModelLabel = 'Ventas';

    public static function form(Form $form): Form
    {
        $updateTotal = function (Forms\Set $set, Forms\Get $get): void {
            $items = $get('items') ?? [];
            $total = 0.0;

            if (is_array($items)) {
                foreach ($items as $item) {
                    $qty = (int) ($item['quantity'] ?? 0);
                    $price = (float) ($item['price'] ?? 0);
                    $total += max(0, $qty) * max(0, $price);
                }
            }

            $set('total', number_format($total, 2, '.', ''));
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
                        Forms\Components\DatePicker::make('sold_on')
                            ->label('Fecha')
                            ->default(now())
                            ->required(),
                        Forms\Components\Select::make('customer_id')
                            ->label('Cliente (opcional)')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('wallet_id')
                            ->label('Billetera')
                            ->options(fn (): array => Wallet::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->default(fn (): ?int => app(WalletService::class)->getDefaultWalletId())
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('payment_method')
                            ->label('Método')
                            ->options([
                                'cash' => 'Efectivo',
                                'transfer' => 'Transferencia',
                                'card' => 'Tarjeta',
                                'yape' => 'Yape/Plin',
                            ])
                            ->default('cash')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'posted' => 'Registrada',
                                'voided' => 'Anulada',
                            ])
                            ->default('posted')
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Productos')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('Items')
                            ->visibleOn('create')
                            ->minItems(1)
                            ->reorderable(false)
                            ->live()
                            ->afterStateUpdated($updateTotal)
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
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                                        $variantId = (int) ($get('product_variant_id') ?? 0);
                                        if ($variantId <= 0) {
                                            return;
                                        }

                                        $variant = ProductVariant::query()->find($variantId);
                                        if (! $variant) {
                                            return;
                                        }

                                        $set('price', (string) ($variant->price ?? 0));
                                        $set('quantity', max(1, (int) ($get('quantity') ?? 1)));
                                    }),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated($updateTotal)
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
                                    ->afterStateUpdated($updateTotal),
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
                        Forms\Components\TextInput::make('total')
                            ->label('Total (calculado)')
                            ->numeric()
                            ->inputMode('decimal')
                            ->disabled()
                            ->dehydrated()
                            ->default('0.00'),
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
                Tables\Columns\TextColumn::make('sold_on')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Método')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'posted' => 'success',
                        'voided' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
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
            ->with(['customer'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'edit' => Pages\EditSale::route('/{record}/edit'),
        ];
    }
}
