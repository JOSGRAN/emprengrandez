<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function createPurchase(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            if (! is_array($items) || count($items) === 0) {
                throw new \InvalidArgumentException('Debe agregar al menos un item.');
            }

            $status = (string) ($data['status'] ?? 'paid');
            if (! in_array($status, ['paid', 'pending', 'voided'], true)) {
                throw new \InvalidArgumentException('Estado inválido.');
            }

            $totalCents = 0;
            foreach ($items as $item) {
                $qty = (int) ($item['quantity'] ?? 0);
                $costCents = CreditService::toCents((string) ($item['cost_price'] ?? 0));

                if ($qty < 1) {
                    throw new \InvalidArgumentException('La cantidad debe ser mayor a 0.');
                }

                if ($costCents <= 0) {
                    throw new \InvalidArgumentException('El costo debe ser mayor a 0.');
                }

                $totalCents += $qty * $costCents;
            }

            $purchase = new Purchase;
            $purchase->supplier_name = $data['supplier_name'] ?? null;
            $purchase->purchased_on = CarbonImmutable::parse((string) ($data['purchased_on'] ?? CarbonImmutable::today()->toDateString()))->toDateString();
            $purchase->status = $status;
            $purchase->wallet_id = $data['wallet_id'] ?? null;
            $purchase->notes = $data['notes'] ?? null;
            $purchase->attachment_path = $data['attachment_path'] ?? null;
            $purchase->created_by = $data['created_by'] ?? null;
            $purchase->total = CreditService::fromCents($totalCents);
            $purchase->save();

            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $variantId = (int) ($item['product_variant_id'] ?? 0);
                $qty = (int) ($item['quantity'] ?? 0);
                $costCents = CreditService::toCents((string) ($item['cost_price'] ?? 0));
                $lineTotalCents = $qty * $costCents;

                $variant = ProductVariant::query()
                    ->lockForUpdate()
                    ->findOrFail($variantId);

                if ((int) $variant->product_id !== $productId) {
                    throw new \InvalidArgumentException('La variante seleccionada no corresponde al producto.');
                }

                $variant->increment('stock', $qty);

                $pi = new PurchaseItem;
                $pi->purchase()->associate($purchase);
                $pi->product_id = $productId;
                $pi->product_variant_id = $variantId;
                $pi->quantity = $qty;
                $pi->cost_price = CreditService::fromCents($costCents);
                $pi->total = CreditService::fromCents($lineTotalCents);
                $pi->save();
            }

            if ($purchase->status === 'paid') {
                $walletId = (int) ($purchase->wallet_id ?? 0);
                if (! $walletId) {
                    $walletId = (int) app(WalletService::class)->getDefaultWalletId();
                    if (! $walletId) {
                        throw new \RuntimeException('No hay una billetera por defecto configurada.');
                    }
                    $purchase->wallet_id = $walletId;
                    $purchase->save();
                }

                $walletService = app(WalletService::class);
                if (! $walletService->existsForReference($walletId, 'purchase', (int) $purchase->id, false)) {
                    $walletService->record(
                        walletId: $walletId,
                        type: 'expense',
                        amount: '-'.(string) $purchase->total,
                        description: 'Compra '.$purchase->code,
                        referenceType: 'purchase',
                        referenceId: (int) $purchase->id,
                        isReversal: false,
                    );
                }
            }

            return $purchase;
        });
    }

    public function pay(Purchase $purchase, int $walletId): Purchase
    {
        return DB::transaction(function () use ($purchase, $walletId) {
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->status !== 'pending') {
                return $purchase;
            }

            $purchase->wallet_id = $walletId;
            $purchase->status = 'paid';
            $purchase->save();

            $walletService = app(WalletService::class);
            if (! $walletService->existsForReference((int) $purchase->wallet_id, 'purchase', (int) $purchase->id, false)) {
                $walletService->record(
                    walletId: (int) $purchase->wallet_id,
                    type: 'expense',
                    amount: '-'.(string) $purchase->total,
                    description: 'Compra '.$purchase->code,
                    referenceType: 'purchase',
                    referenceId: (int) $purchase->id,
                    isReversal: false,
                );
            }

            return $purchase;
        });
    }

    public function void(Purchase $purchase, ?int $voidedBy = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $voidedBy) {
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->status === 'voided') {
                return $purchase;
            }

            $items = PurchaseItem::query()->where('purchase_id', $purchase->id)->get();

            foreach ($items as $item) {
                $variant = ProductVariant::query()
                    ->lockForUpdate()
                    ->findOrFail($item->product_variant_id);

                $variant->decrement('stock', (int) $item->quantity);
            }

            if ($purchase->wallet_id) {
                app(WalletService::class)->deleteTransactionForReference(
                    walletId: (int) $purchase->wallet_id,
                    referenceType: 'purchase',
                    referenceId: (int) $purchase->id,
                );
            }

            $purchase->status = 'voided';
            $purchase->notes = trim((string) ($purchase->notes ?? '')."\n".'Anulada por usuario #'.(string) ($voidedBy ?? ''));
            $purchase->save();

            return $purchase;
        });
    }
}
