<?php

namespace App\Filament\Widgets;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class WalletStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $month = CarbonImmutable::today()->format('Y-m');
        $cacheKey = 'dashboard:wallet-stats:'.$month;

        /** @var array{balance:float,income:float,expense:float,net:float} $data */
        $data = Cache::remember($cacheKey, 60, function () use ($month) {
            $balance = (float) Wallet::query()->where('is_active', true)->sum('balance');

            $from = CarbonImmutable::parse($month.'-01')->startOfDay();
            $to = $from->endOfMonth()->endOfDay();

            $tx = WalletTransaction::query()
                ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()])
                ->get(['amount']);

            $income = (float) $tx->where('amount', '>', 0)->sum('amount');
            $expense = (float) $tx->where('amount', '<', 0)->sum('amount');
            $net = $income + $expense;

            return [
                'balance' => $balance,
                'income' => $income,
                'expense' => abs($expense),
                'net' => $net,
            ];
        });

        return [
            Stat::make('Dinero disponible', 'S/ '.number_format($data['balance'], 2, '.', ','))
                ->color('primary'),
            Stat::make('Ingresos del mes', 'S/ '.number_format($data['income'], 2, '.', ','))
                ->color('success'),
            Stat::make('Gastos del mes', 'S/ '.number_format($data['expense'], 2, '.', ','))
                ->color('danger'),
            Stat::make('Neto del mes', 'S/ '.number_format($data['net'], 2, '.', ','))
                ->color($data['net'] < 0 ? 'danger' : 'success'),
        ];
    }
}
