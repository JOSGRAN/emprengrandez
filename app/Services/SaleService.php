<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function createSale(?Customer $customer, array $data): Sale
    {
        return DB::transaction(function () use ($customer, $data) {
            $items = $data['items'] ?? [];

            if (! is_array($items) || count($items) === 0) {
                throw new \InvalidArgumentException('Debe agregar al menos un producto.');
            }

            $totalCents = 0;
            foreach ($items as $item) {
                $quantity = (int) ($item['quantity'] ?? 0);
                $priceCents = CreditService::toCents((string) ($item['price'] ?? 0));

                if ($quantity < 1) {
                    throw new \InvalidArgumentException('La cantidad debe ser mayor a 0.');
                }

                if ($priceCents <= 0) {
                    throw new \InvalidArgumentException('El precio debe ser mayor a 0.');
                }

                $totalCents += ($priceCents * $quantity);
            }

            if ($totalCents <= 0) {
                throw new \InvalidArgumentException('El total debe ser mayor a 0.');
            }

            $sale = new Sale;
            if ($customer) {
                $sale->customer()->associate($customer);
            }
            $sale->wallet_id = (int) ($data['wallet_id'] ?? app(WalletService::class)->getDefaultWalletId());
            if (! $sale->wallet_id) {
                throw new \RuntimeException('No hay una billetera por defecto configurada.');
            }
            $sale->sold_on = $data['sold_on'];
            $sale->total = CreditService::fromCents($totalCents);
            $sale->payment_method = $data['payment_method'] ?? 'cash';
            $sale->status = $data['status'] ?? 'posted';
            $sale->notes = $data['notes'] ?? null;
            $sale->created_by = $data['created_by'] ?? null;
            $sale->save();

            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $variantId = (int) ($item['product_variant_id'] ?? 0);
                $quantity = (int) ($item['quantity'] ?? 0);
                $priceCents = CreditService::toCents((string) ($item['price'] ?? 0));
                $lineTotalCents = $priceCents * $quantity;

                $variant = ProductVariant::query()
                    ->lockForUpdate()
                    ->findOrFail($variantId);

                if ($variant->product_id !== $productId) {
                    throw new \InvalidArgumentException('La variante seleccionada no corresponde al producto.');
                }

                if ((int) $variant->stock < $quantity) {
                    throw new \RuntimeException('Stock insuficiente para la variante seleccionada.');
                }

                $variant->decrement('stock', $quantity);

                $saleItem = new SaleItem;
                $saleItem->sale()->associate($sale);
                $saleItem->product_id = $productId;
                $saleItem->product_variant_id = $variantId;
                $saleItem->quantity = $quantity;
                $saleItem->price = CreditService::fromCents($priceCents);
                $saleItem->total = CreditService::fromCents($lineTotalCents);
                $saleItem->save();
            }

            if ($sale->status === 'posted') {
                $walletService = app(WalletService::class);
                if (! $walletService->existsForReference($sale->wallet_id, 'sale', $sale->id, false)) {
                    $walletService->record(
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

            return $sale;
        });
    }
}
