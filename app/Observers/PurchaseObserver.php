<?php

namespace App\Observers;

use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;

class PurchaseObserver
{
    public function updating(Purchase $purchase): void
    {
        if (! $purchase->isDirty('status')) {
            return;
        }

        if ($purchase->status !== 'paid') {
            return;
        }

        if ($purchase->wallet_id) {
            return;
        }

        $walletId = app(WalletService::class)->getDefaultWalletId();
        if ($walletId) {
            $purchase->wallet_id = $walletId;
        }
    }

    public function updated(Purchase $purchase): void
    {
        if (! $purchase->wasChanged('status')) {
            return;
        }

        if ($purchase->status !== 'paid') {
            return;
        }

        if (! $purchase->wallet_id) {
            return;
        }

        $service = app(WalletService::class);
        if ($service->existsForReference((int) $purchase->wallet_id, 'purchase', (int) $purchase->id, false)) {
            return;
        }

        $service->record(
            walletId: (int) $purchase->wallet_id,
            type: 'expense',
            amount: '-'.(string) $purchase->total,
            description: 'Compra '.$purchase->code,
            referenceType: 'purchase',
            referenceId: (int) $purchase->id,
            isReversal: false,
        );
    }

    public function deleting(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $items = PurchaseItem::query()->where('purchase_id', $purchase->id)->get();

            foreach ($items as $item) {
                $variant = ProductVariant::query()
                    ->lockForUpdate()
                    ->find($item->product_variant_id);

                if ($variant) {
                    $variant->decrement('stock', (int) $item->quantity);
                }
            }

            if ($purchase->wallet_id) {
                app(WalletService::class)->deleteTransactionForReference(
                    walletId: (int) $purchase->wallet_id,
                    referenceType: 'purchase',
                    referenceId: (int) $purchase->id,
                );
            }
        });
    }
}
