<?php

namespace App\Filament\Widgets;

use App\Models\Credit;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class FinanceStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $today = now()->toDateString();
        $cacheKey = 'dashboard:finance-stats:'.$today;

        /** @var array{today_income:float,today_payments_count:int,today_sales_count:int,active_credits_count:int,overdue_installments_count:int,customers_with_debt_count:int} $data */
        $data = Cache::remember($cacheKey, 60, function () use ($today) {
            $todayPaymentsIncome = (float) Payment::query()
                ->where('status', 'posted')
                ->whereDate('paid_on', $today)
                ->sum('amount');

            $todaySalesIncome = (float) Sale::query()
                ->where('status', 'posted')
                ->whereDate('sold_on', $today)
                ->sum('total');

            $todayIncome = $todayPaymentsIncome + $todaySalesIncome;

            $todayPaymentsCount = (int) Payment::query()
                ->where('status', 'posted')
                ->whereDate('paid_on', $today)
                ->count();

            $todaySalesCount = (int) Sale::query()
                ->where('status', 'posted')
                ->whereDate('sold_on', $today)
                ->count();

            $activeCreditsCount = (int) Credit::query()
                ->where('status', 'active')
                ->count();

            $overdueInstallmentsCount = (int) Installment::query()
                ->where('status', 'overdue')
                ->count();

            $customersWithDebtCount = (int) Credit::query()
                ->where('status', 'active')
                ->where('balance', '>', 0)
                ->distinct('customer_id')
                ->count('customer_id');

            return [
                'today_income' => $todayIncome,
                'today_payments_count' => $todayPaymentsCount,
                'today_sales_count' => $todaySalesCount,
                'active_credits_count' => $activeCreditsCount,
                'overdue_installments_count' => $overdueInstallmentsCount,
                'customers_with_debt_count' => $customersWithDebtCount,
            ];
        });

        return [
            Stat::make('Ingresos del día', 'S/ '.number_format($data['today_income'], 2, '.', ',')),
            Stat::make('Ventas del día', number_format($data['today_sales_count'], 0, '.', ',')),
            Stat::make('Pagos del día', number_format($data['today_payments_count'], 0, '.', ',')),
            Stat::make('Créditos activos', number_format($data['active_credits_count'], 0, '.', ',')),
            Stat::make('Cuotas vencidas', number_format($data['overdue_installments_count'], 0, '.', ',')),
            Stat::make('Clientes con deuda', number_format($data['customers_with_debt_count'], 0, '.', ',')),
        ];
    }
}
