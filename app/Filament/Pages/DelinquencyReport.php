<?php

namespace App\Filament\Pages;

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
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DelinquencyReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?string $title = 'Morosidad';

    protected static string $view = 'filament.pages.delinquency-report';

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
            ->query($this->delinquencyQuery())
            ->defaultSort('overdue_amount', 'desc')
            ->columns([
                TextColumn::make('customer_code')->label('Código')->searchable(),
                TextColumn::make('customer_name')->label('Cliente')->searchable(),
                TextColumn::make('overdue_installments')->label('Cuotas vencidas')->sortable(),
                TextColumn::make('overdue_amount')->label('Total en mora')->money('PEN')->sortable(),
                TextColumn::make('oldest_due_date')->label('Primera cuota')->date()->toggleable(),
                TextColumn::make('latest_due_date')->label('Última cuota')->date()->toggleable(),
            ])
            ->paginated(true);
    }

    public function getTotals(): array
    {
        $totals = DB::query()
            ->fromSub($this->delinquencyQuery(), 't')
            ->selectRaw('COALESCE(SUM(overdue_installments), 0) as overdue_installments')
            ->selectRaw('COALESCE(SUM(overdue_amount), 0) as overdue_amount')
            ->selectRaw('COUNT(*) as customers')
            ->first();

        return [
            'customers' => (int) ($totals->customers ?? 0),
            'overdue_installments' => (int) ($totals->overdue_installments ?? 0),
            'overdue_amount' => (float) ($totals->overdue_amount ?? 0),
        ];
    }

    private function delinquencyQuery(): Builder
    {
        $from = (string) ($this->data['from'] ?? CarbonImmutable::today()->subDays(29)->toDateString());
        $to = (string) ($this->data['to'] ?? CarbonImmutable::today()->toDateString());

        return DB::table('installments')
            ->join('credits', 'credits.id', '=', 'installments.credit_id')
            ->join('customers', 'customers.id', '=', 'credits.customer_id')
            ->where('installments.status', 'overdue')
            ->whereBetween('installments.due_date', [$from, $to])
            ->groupBy('customers.id', 'customers.code', 'customers.name')
            ->selectRaw('customers.code as customer_code')
            ->selectRaw('customers.name as customer_name')
            ->selectRaw('COUNT(*) as overdue_installments')
            ->selectRaw('SUM(CASE WHEN (installments.total_amount - installments.paid_amount) > 0 THEN (installments.total_amount - installments.paid_amount) ELSE 0 END) as overdue_amount')
            ->selectRaw('MIN(installments.due_date) as oldest_due_date')
            ->selectRaw('MAX(installments.due_date) as latest_due_date');
    }
}
