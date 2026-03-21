<?php

namespace App\Filament\Widgets;

use App\Models\Credit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\BarChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreditsGeneratedChartWidget extends BarChartWidget
{
    protected static ?string $heading = 'Créditos generados (últimos 30 días)';

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
        $cacheKey = 'dashboard:credits-generated:'.$start->toDateString().':'.$end->toDateString().':'.$filter;

        return Cache::remember($cacheKey, 60, function () use ($start, $end, $filter) {
            $labels = [];
            $days = [];

            for ($d = $start; $d <= $end; $d = $d->addDay()) {
                $key = $d->toDateString();
                $labels[] = $d->format('d/m');
                $days[$key] = 0;
            }

            $query = Credit::query()
                ->whereBetween('created_at', [
                    $start->startOfDay()->toDateTimeString(),
                    $end->endOfDay()->toDateTimeString(),
                ]);

            if ($filter !== 'all') {
                $query->where('created_by', (int) $filter);
            }

            $rows = $query->select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as total'))
                ->groupBy('d')
                ->pluck('total', 'd')
                ->all();

            $data = [];
            foreach (array_keys($days) as $day) {
                $data[] = (int) ($rows[$day] ?? 0);
            }

            return [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Créditos',
                        'data' => $data,
                        'backgroundColor' => '#9333ea',
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
