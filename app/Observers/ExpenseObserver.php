<?php

namespace App\Observers;

use App\Models\Expense;
use App\Services\WalletService;

class ExpenseObserver
{
    public function creating(Expense $expense): void
    {
        if ($expense->wallet_id) {
            return;
        }

        $walletId = app(WalletService::class)->getDefaultWalletId();
        if ($walletId) {
            $expense->wallet_id = $walletId;
        }
    }

    public function created(Expense $expense): void
    {
        if (! $expense->wallet_id) {
            return;
        }

        $service = app(WalletService::class);
        if ($service->existsForReference($expense->wallet_id, 'expense', $expense->id, false)) {
            return;
        }

        $service->record(
            walletId: $expense->wallet_id,
            type: 'expense',
            amount: '-'.(string) $expense->amount,
            description: 'Gasto '.$expense->code,
            referenceType: 'expense',
            referenceId: $expense->id,
            isReversal: false,
        );
    }

    public function deleting(Expense $expense): void
    {
        if (! $expense->wallet_id) {
            return;
        }

        app(WalletService::class)->deleteTransactionForReference(
            walletId: (int) $expense->wallet_id,
            referenceType: 'expense',
            referenceId: (int) $expense->id,
        );
    }
}
