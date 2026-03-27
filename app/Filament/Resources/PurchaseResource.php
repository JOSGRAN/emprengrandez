<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseResource\Pages;
use App\Filament\Resources\PurchaseResource\RelationManagers\ItemsRelationManager;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Wallet;
use App\Services\ImageService;
use App\Services\WalletService;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Compras';

    protected static ?string $modelLabel = 'Compra';

    protected static ?string $pluralModelLabel = 'Compras';

    public static function form(Form $form): Form
    {
        $updateTotal = function (Forms\Set $set, Forms\Get $get): void {
            $items = $get('items') ?? [];
            $total = 0.0;

            if (is_array($items)) {
                foreach ($items as $item) {
                    $qty = (int) ($item['quantity'] ?? 0);
                    $cost = (float) ($item['cost_price'] ?? 0);
                    $total += max(0, $qty) * max(0, $cost);
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
                        Forms\Components\FileUpload::make('attachment_path')
                            ->label('Comprobante')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png'])
                            ->maxSize(5120)
                            ->disk('public')
                            ->directory('purchases')
                            ->visibility('public')
                            ->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
                                return app(ImageService::class)->storeWebp(
                                    file: $file,
                                    folder: (string) ($component->getDirectory() ?? 'purchases'),
                                    quality: 80,
                                );
                            })
                            ->columnSpanFull(),
                        Forms\Components\DatePicker::make('purchased_on')
                            ->label('Fecha')
                            ->default(now())
                            ->required(),
                        Forms\Components\TextInput::make('supplier_name')
                            ->label('Proveedor (opcional)')
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'paid' => 'Pagada',
                                'pending' => 'Pendiente',
                                'voided' => 'Anulada',
                            ])
                            ->default('paid')
                            ->required()
                            ->live()
                            ->visibleOn('create'),
                        Forms\Components\Placeholder::make('status_view')
                            ->label('Estado')
                            ->visibleOn('edit')
                            ->content(fn (?Purchase $record): string => match ($record?->status) {
                                'paid' => 'Pagada',
                                'pending' => 'Pendiente',
                                'voided' => 'Anulada',
                                default => (string) ($record?->status ?? '-'),
                            }),
                        Forms\Components\Select::make('wallet_id')
                            ->label('Billetera')
                            ->options(fn (): array => Wallet::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->default(fn (): ?int => app(WalletService::class)->getDefaultWalletId())
                            ->searchable()
                            ->required(fn (Forms\Get $get): bool => ($get('status') ?? 'paid') === 'paid')
                            ->visibleOn('create'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Items')
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
                                        $set('quantity', 1);
                                        $set('cost_price', null);
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
                                            ->get(['id', 'size', 'color', 'stock'])
                                            ->mapWithKeys(function (ProductVariant $v) {
                                                $parts = array_filter([$v->size, $v->color]);
                                                $label = trim(implode(' / ', $parts));
                                                $label = $label !== '' ? $label : ('Variante #'.$v->id);
                                                $label .= ' — Stock: '.(int) $v->stock;

                                                return [$v->id => $label];
                                            })
                                            ->all();
                                    })
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) use ($updateTotal) {
                                        $variantId = (int) ($get('product_variant_id') ?? 0);
                                        if ($variantId <= 0) {
                                            return;
                                        }

                                        $lastCost = PurchaseItem::query()
                                            ->where('product_variant_id', $variantId)
                                            ->latest('id')
                                            ->value('cost_price');

                                        if ($lastCost !== null && (float) $lastCost > 0) {
                                            $set('cost_price', (string) $lastCost);
                                            $updateTotal($set, $get);

                                            return;
                                        }

                                        $productId = (int) ($get('product_id') ?? 0);
                                        if ($productId > 0) {
                                            $productCost = Product::query()->whereKey($productId)->value('cost');
                                            if ($productCost !== null && (float) $productCost > 0) {
                                                $set('cost_price', (string) $productCost);
                                                $updateTotal($set, $get);
                                            }
                                        }
                                    }),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated($updateTotal),
                                Forms\Components\TextInput::make('cost_price')
                                    ->label('Costo (unit)')
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
                                        $cost = (float) ($get('cost_price') ?? 0);
                                        $total = max(0, $qty) * max(0, $cost);

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
                Tables\Columns\ImageColumn::make('attachment_path')
                    ->label('Comprobante')
                    ->disk('public')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('purchased_on')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplier_name')
                    ->label('Proveedor')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
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
            'index' => Pages\ListPurchases::route('/'),
            'create' => Pages\CreatePurchase::route('/create'),
            'edit' => Pages\EditPurchase::route('/{record}/edit'),
        ];
    }
}
