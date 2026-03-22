<?php

namespace App\Observers;

use App\Models\Sale;
use App\Services\WalletService;

class SaleObserver
{
    public function creating(Sale $sale): void
    {
        if ($sale->wallet_id) {
            return;
        }

        $walletId = app(WalletService::class)->getDefaultWalletId();
        if ($walletId) {
            $sale->wallet_id = $walletId;
        }
    }

    public function created(Sale $sale): void
    {
        if ($sale->status !== 'posted') {
            return;
        }

        if (! $sale->wallet_id) {
            return;
        }

        $service = app(WalletService::class);
        if ($service->existsForReference($sale->wallet_id, 'sale', $sale->id, false)) {
            return;
        }

        $service->record(
            walletId: $sale->wallet_id,
            type: 'income',
            amount: (string) $sale->total,
            description: 'Venta '.$sale->code,
            referenceType: 'sale',
            referenceId: $sale->id,
            isReversal: false,
        );
    }
}
