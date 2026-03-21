<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\BarChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PaymentsByDayChartWidget extends BarChartWidget
{
    protected static ?string $heading = 'Pagos por día (últimos 30 días)';

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
        $cacheKey = 'dashboard:payments-by-day:'.$start->toDateString().':'.$end->toDateString().':'.$filter;

        return Cache::remember($cacheKey, 60, function () use ($start, $end, $filter) {
            $labels = [];
            $days = [];

            for ($d = $start; $d <= $end; $d = $d->addDay()) {
                $key = $d->toDateString();
                $labels[] = $d->format('d/m');
                $days[$key] = 0;
            }

            $query = Payment::query()
                ->where('status', 'posted')
                ->whereBetween('paid_on', [$start->toDateString(), $end->toDateString()]);

            if ($filter !== 'all') {
                $query->where('created_by', (int) $filter);
            }

            $rows = $query->select(DB::raw('DATE(paid_on) as d'), DB::raw('SUM(amount) as total'))
                ->groupBy('d')
                ->pluck('total', 'd')
                ->all();

            $data = [];
            foreach (array_keys($days) as $day) {
                $data[] = (float) ($rows[$day] ?? 0);
            }

            return [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Pagos (S/)',
                        'data' => $data,
                        'backgroundColor' => '#2563eb',
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
