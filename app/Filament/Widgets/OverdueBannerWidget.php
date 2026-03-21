<?php

namespace App\Filament\Widgets;

use App\Models\Installment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class OverdueBannerWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $today = now()->toDateString();
        $cacheKey = 'dashboard:overdue-banner:'.$today;

        /** @var array{overdue_count:int,overdue_amount:float} $data */
        $data = Cache::remember($cacheKey, 60, function () {
            $rows = Installment::query()
                ->where('status', 'overdue')
                ->get(['total_amount', 'paid_amount']);

            $amount = (float) $rows->sum(fn ($i) => max(0, (float) $i->total_amount - (float) $i->paid_amount));

            return [
                'overdue_count' => (int) $rows->count(),
                'overdue_amount' => $amount,
            ];
        });

        $count = $data['overdue_count'];
        $amount = $data['overdue_amount'];

        $pulse = $count > 0 ? ['class' => 'animate-pulse'] : [];

        return [
            Stat::make('Cuotas vencidas', number_format($count, 0, '.', ','))
                ->description('Revisar cobranza')
                ->descriptionIcon('heroicon-s-exclamation-triangle')
                ->color($count > 0 ? 'danger' : 'gray')
                ->extraAttributes($pulse),
            Stat::make('Monto en mora', 'S/ '.number_format($amount, 2, '.', ','))
                ->description('Total pendiente vencido')
                ->descriptionIcon('heroicon-s-exclamation-triangle')
                ->color($amount > 0 ? 'danger' : 'gray')
                ->extraAttributes($pulse),
        ];
    }
}
