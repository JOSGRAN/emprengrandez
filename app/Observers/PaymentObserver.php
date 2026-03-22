<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\WalletService;

class PaymentObserver
{
    public function creating(Payment $payment): void
    {
        if ($payment->wallet_id) {
            return;
        }

        $walletId = app(WalletService::class)->getDefaultWalletId();
        if ($walletId) {
            $payment->wallet_id = $walletId;
        }
    }

    public function created(Payment $payment): void
    {
        if ($payment->status !== 'posted') {
            return;
        }

        if (! $payment->wallet_id) {
            return;
        }

        $service = app(WalletService::class);
        if ($service->existsForReference($payment->wallet_id, 'payment', $payment->id, false)) {
            return;
        }

        $service->record(
            walletId: $payment->wallet_id,
            type: 'income',
            amount: (string) $payment->amount,
            description: 'Pago '.$payment->code,
            referenceType: 'payment',
            referenceId: $payment->id,
            isReversal: false,
        );
    }
}
