<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TopDebtCustomersWidget extends BaseWidget
{
    protected static ?string $heading = 'Top clientes con deuda';

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        $sub = DB::table('credits')
            ->where('status', 'active')
            ->where('balance', '>', 0)
            ->selectRaw('customer_id, SUM(balance) as debt, COUNT(*) as credits_count')
            ->groupBy('customer_id');

        return Customer::query()
            ->joinSub($sub, 't', 't.customer_id', '=', 'customers.id')
            ->select('customers.*')
            ->selectRaw('t.debt as debt')
            ->selectRaw('t.credits_count as credits_count')
            ->orderByDesc('t.debt');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Cliente')
                    ->searchable(),
                Tables\Columns\TextColumn::make('credits_count')
                    ->label('Créditos')
                    ->sortable(),
                Tables\Columns\TextColumn::make('debt')
                    ->label('Deuda')
                    ->money('PEN')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Abrir')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Customer $record): string => CustomerResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(),
            ]);
    }
}
