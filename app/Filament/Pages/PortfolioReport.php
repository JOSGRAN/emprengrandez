<?php

namespace App\Filament\Pages;

use App\Models\Credit;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PortfolioReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?string $title = 'Estado de cartera';

    protected static string $view = 'filament.pages.portfolio-report';

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
            ->query($this->creditsQuery())
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->label('Código')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Cliente')->searchable()->sortable(),
                TextColumn::make('principal_amount')->label('Prestado')->money('PEN')->sortable(),
                TextColumn::make('total_amount')->label('Total')->money('PEN')->sortable(),
                TextColumn::make('balance')->label('Pendiente')->money('PEN')->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'warning',
                        'closed' => 'success',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->label('Creado')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'active' => 'Activo',
                        'closed' => 'Cerrado',
                        'cancelled' => 'Cancelado',
                    ])
                    ->default('active'),
            ]);
    }

    public function getTotals(): array
    {
        $from = (string) ($this->data['from'] ?? CarbonImmutable::today()->subDays(29)->toDateString());
        $to = (string) ($this->data['to'] ?? CarbonImmutable::today()->toDateString());

        $totalLoaned = (float) DB::table('credits')
            ->whereBetween('start_date', [$from, $to])
            ->sum('principal_amount');

        $totalRecovered = (float) DB::table('payments')
            ->where('status', 'posted')
            ->whereBetween('paid_on', [$from, $to])
            ->sum('amount');

        $totalPending = (float) DB::table('credits')
            ->where('status', 'active')
            ->sum('balance');

        return [
            'loaned' => $totalLoaned,
            'recovered' => $totalRecovered,
            'pending' => $totalPending,
        ];
    }

    private function creditsQuery(): Builder
    {
        $from = (string) ($this->data['from'] ?? CarbonImmutable::today()->subDays(29)->toDateString());
        $to = (string) ($this->data['to'] ?? CarbonImmutable::today()->toDateString());

        return Credit::query()
            ->whereBetween('start_date', [$from, $to]);
    }
}
