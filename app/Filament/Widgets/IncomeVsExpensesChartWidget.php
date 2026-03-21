<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\LineChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class IncomeVsExpensesChartWidget extends LineChartWidget
{
    protected static ?string $heading = 'Ingresos vs gastos (últimos 30 días)';

    protected int|string|array $columnSpan = 'full';

    protected function getFilters(): ?array
    {
        return [
            'all' => 'Todos',
            ...self::sellerOptions(),
        ];
    }

    protected function getData(): array
    {
        $start = CarbonImmutable::today()->subDays(29);
        $end = CarbonImmutable::today();
        $filter = $this->filter ?? 'all';
        $cacheKey = 'dashboard:income-vs-expenses:'.$start->toDateString().':'.$end->toDateString().':'.$filter;

        return Cache::remember($cacheKey, 60, function () use ($start, $end, $filter) {
            $labels = [];
            $days = [];

            for ($d = $start; $d <= $end; $d = $d->addDay()) {
                $key = $d->toDateString();
                $labels[] = $d->format('d/m');
                $days[$key] = 0;
            }

            $incomeQuery = Payment::query()
                ->where('status', 'posted')
                ->whereBetween('paid_on', [$start->toDateString(), $end->toDateString()]);

            if ($filter !== 'all') {
                $incomeQuery->where('created_by', (int) $filter);
            }

            $incomeRows = $incomeQuery
                ->select(DB::raw('DATE(paid_on) as d'), DB::raw('SUM(amount) as total'))
                ->groupBy('d')
                ->pluck('total', 'd')
                ->all();

            $expenseQuery = Expense::query()
                ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()]);

            if ($filter !== 'all') {
                $expenseQuery->where('created_by', (int) $filter);
            }

            $expenseRows = $expenseQuery
                ->select(DB::raw('DATE(occurred_on) as d'), DB::raw('SUM(amount) as total'))
                ->groupBy('d')
                ->pluck('total', 'd')
                ->all();

            $income = [];
            $expenses = [];

            foreach (array_keys($days) as $day) {
                $income[] = (float) ($incomeRows[$day] ?? 0);
                $expenses[] = (float) ($expenseRows[$day] ?? 0);
            }

            return [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Ingresos',
                        'data' => $income,
                        'borderColor' => '#16a34a',
                        'backgroundColor' => 'rgba(22,163,74,0.2)',
                        'tension' => 0.2,
                    ],
                    [
                        'label' => 'Gastos',
                        'data' => $expenses,
                        'borderColor' => '#dc2626',
                        'backgroundColor' => 'rgba(220,38,38,0.2)',
                        'tension' => 0.2,
                    ],
                ],
            ];
        });
    }

    private static function sellerOptions(): array
    {
        return Cache::remember('dashboard:seller-options', 300, function (): array {
            return User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'vendedor']))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->mapWithKeys(fn (User $u) => [(string) $u->id => $u->name])
                ->all();
        });
    }
}
