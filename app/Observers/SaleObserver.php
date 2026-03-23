<?php

namespace App\Observers;

use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;

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

    public function deleting(Sale $sale): void
    {
        if ($sale->status !== 'posted') {
            return;
        }

        DB::transaction(function () use ($sale) {
            $items = SaleItem::query()->where('sale_id', $sale->id)->get();

            foreach ($items as $item) {
                $variant = ProductVariant::query()
                    ->lockForUpdate()
                    ->find($item->product_variant_id);

                if ($variant) {
                    $variant->increment('stock', (int) $item->quantity);
                }
            }

            if ($sale->wallet_id) {
                app(WalletService::class)->deleteTransactionForReference(
                    walletId: (int) $sale->wallet_id,
                    referenceType: 'sale',
                    referenceId: (int) $sale->id,
                );
            }

            $sale->status = 'voided';
            $sale->saveQuietly();
        });
    }
}
