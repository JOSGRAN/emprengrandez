<?php

namespace App\Filament\Pages;

use App\Models\Payment;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;

class CashFlowReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?string $title = 'Flujo de caja';

    protected static string $view = 'filament.pages.cash-flow-report';

    public ?array $data = [];

    public function mount(): void
    {
        $start = CarbonImmutable::today()->subDays(29);
        $end = CarbonImmutable::today();

        $this->form->fill([
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Rango')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Desde')
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetTable()),
                        DatePicker::make('to')
                            ->label('Hasta')
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetTable()),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->cashFlowQuery())
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Fecha')
                    ->date(),
                TextColumn::make('income')
                    ->label('Ingresos')
                    ->money('PEN'),
                TextColumn::make('expense')
                    ->label('Egresos')
                    ->money('PEN'),
                TextColumn::make('net')
                    ->label('Neto')
                    ->money('PEN')
                    ->color(fn (string $state): string => ((float) $state) < 0 ? 'danger' : 'success'),
            ])
            ->paginated(false);
    }

    public function getTotals(): array
    {
        $totals = DB::query()
            ->fromSub($this->cashFlowQuery()->toBase(), 't')
            ->selectRaw('COALESCE(SUM(income), 0) as income')
            ->selectRaw('COALESCE(SUM(expense), 0) as expense')
            ->selectRaw('COALESCE(SUM(net), 0) as net')
            ->first();

        return [
            'income' => (float) ($totals->income ?? 0),
            'expense' => (float) ($totals->expense ?? 0),
            'net' => (float) ($totals->net ?? 0),
        ];
    }

    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->getAttribute('date');
    }

    private function cashFlowQuery(): Builder
    {
        $from = (string) ($this->data['from'] ?? CarbonImmutable::today()->subDays(29)->toDateString());
        $to = (string) ($this->data['to'] ?? CarbonImmutable::today()->toDateString());

        $incomePaymentsSub = DB::table('payments')
            ->where('status', 'posted')
            ->whereBetween('paid_on', [$from, $to])
            ->selectRaw('DATE(paid_on) as date, SUM(amount) as income')
            ->groupBy('date');

        $incomeSalesSub = DB::table('sales')
            ->where('status', 'posted')
            ->whereBetween('sold_on', [$from, $to])
            ->selectRaw('DATE(sold_on) as date, SUM(total) as income')
            ->groupBy('date');

        $incomeSub = DB::query()
            ->fromSub(
                DB::query()
                    ->fromSub($incomePaymentsSub, 'p')
                    ->selectRaw('date, income')
                    ->unionAll(
                        DB::query()->fromSub($incomeSalesSub, 's')->selectRaw('date, income'),
                    ),
                'income_rows',
            )
            ->selectRaw('date, SUM(income) as income')
            ->groupBy('date');

        $expenseSub = DB::table('expenses')
            ->whereBetween('occurred_on', [$from, $to])
            ->selectRaw('DATE(occurred_on) as date, SUM(amount) as expense')
            ->groupBy('date');

        $datesSub = DB::query()
            ->fromSub(
                DB::query()
                    ->fromSub($incomeSub, 'i')->select('date')
                    ->union(
                        DB::query()->fromSub($expenseSub, 'e')->select('date'),
                    ),
                'dates',
            )
            ->select('date')
            ->distinct();

        $base = DB::query()
            ->fromSub($datesSub, 'd')
            ->leftJoinSub($incomeSub, 'i', 'i.date', '=', 'd.date')
            ->leftJoinSub($expenseSub, 'e', 'e.date', '=', 'd.date')
            ->selectRaw('d.date as date')
            ->selectRaw('COALESCE(i.income, 0) as income')
            ->selectRaw('COALESCE(e.expense, 0) as expense')
            ->selectRaw('COALESCE(i.income, 0) - COALESCE(e.expense, 0) as net');

        return Payment::query()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->fromSub($base, 't')
            ->selectRaw('t.date as date')
            ->selectRaw('t.income as income')
            ->selectRaw('t.expense as expense')
            ->selectRaw('t.net as net');
    }
}
