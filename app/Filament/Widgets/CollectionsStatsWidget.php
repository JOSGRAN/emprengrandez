<?php

namespace App\Filament\Widgets;

use App\Models\Installment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class CollectionsStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $today = now()->toDateString();
        $cacheKey = 'dashboard:collections-stats:'.$today;

        /** @var array{expected_today_amount:float,expected_today_count:int,overdue_amount:float,overdue_count:int} $data */
        $data = Cache::remember($cacheKey, 60, function () use ($today) {
            $expectedToday = Installment::query()
                ->where('status', 'pending')
                ->whereDate('due_date', $today)
                ->get(['total_amount', 'paid_amount']);

            $expectedTodayAmount = (float) $expectedToday->sum(fn ($i) => max(0, (float) $i->total_amount - (float) $i->paid_amount));
            $expectedTodayCount = (int) $expectedToday->count();

            $overdue = Installment::query()
                ->where('status', 'overdue')
                ->get(['total_amount', 'paid_amount']);

            $overdueAmount = (float) $overdue->sum(fn ($i) => max(0, (float) $i->total_amount - (float) $i->paid_amount));
            $overdueCount = (int) $overdue->count();

            return [
                'expected_today_amount' => $expectedTodayAmount,
                'expected_today_count' => $expectedTodayCount,
                'overdue_amount' => $overdueAmount,
                'overdue_count' => $overdueCount,
            ];
        });

        return [
            Stat::make('Cobros esperados hoy', 'S/ '.number_format($data['expected_today_amount'], 2, '.', ','))
                ->description(number_format($data['expected_today_count'], 0, '.', ',').' cuotas')
                ->color('primary'),
            Stat::make('Cobros atrasados', 'S/ '.number_format($data['overdue_amount'], 2, '.', ','))
                ->description(number_format($data['overdue_count'], 0, '.', ',').' cuotas')
                ->color('danger'),
        ];
    }
}
