<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function getDefaultWalletId(): ?int
    {
        $id = Setting::getInt('wallet.default_wallet_id', 0);
        if ($id > 0 && Wallet::query()->whereKey($id)->exists()) {
            return $id;
        }

        $wallet = Wallet::query()->where('is_active', true)->orderBy('id')->first();

        return $wallet?->id;
    }

    public function record(
        int $walletId,
        string $type,
        string $amount,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        bool $isReversal = false,
    ): WalletTransaction {
        return DB::transaction(function () use ($walletId, $type, $amount, $description, $referenceType, $referenceId, $isReversal) {
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($walletId);
            $allowNegative = Setting::getBool('wallet.allow_negative', true);

            $amountCents = CreditService::toCents($amount);
            if ($amountCents === 0) {
                throw new \InvalidArgumentException('El monto no puede ser 0.');
            }

            $newBalanceCents = CreditService::toCents((string) $wallet->balance) + $amountCents;
            if (! $allowNegative && $newBalanceCents < 0) {
                throw new \RuntimeException('Saldo insuficiente en la billetera.');
            }

            $transaction = WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => $type,
                'amount' => CreditService::fromCents($amountCents),
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'is_reversal' => $isReversal,
            ]);

            $wallet->balance = CreditService::fromCents($newBalanceCents);
            $wallet->save();

            $this->forgetDashboardCaches();

            return $transaction;
        });
    }

    public function existsForReference(int $walletId, string $referenceType, int $referenceId, bool $isReversal): bool
    {
        return WalletTransaction::query()
            ->where('wallet_id', $walletId)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('is_reversal', $isReversal)
            ->exists();
    }

    public function deleteTransactionForReference(int $walletId, string $referenceType, int $referenceId): void
    {
        DB::transaction(function () use ($walletId, $referenceType, $referenceId) {
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($walletId);

            WalletTransaction::query()
                ->where('wallet_id', $walletId)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->where('is_reversal', false)
                ->delete();

            $this->syncWalletBalance($wallet->id);
            $this->forgetDashboardCaches();
        });
    }

    public function syncWalletBalance(int $walletId): void
    {
        DB::transaction(function () use ($walletId) {
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($walletId);

            $sumCents = (int) WalletTransaction::query()
                ->where('wallet_id', $walletId)
                ->get(['amount'])
                ->sum(fn (WalletTransaction $tx): int => CreditService::toCents((string) $tx->amount));

            $wallet->balance = CreditService::fromCents($sumCents);
            $wallet->save();
        });
    }

    private function forgetDashboardCaches(): void
    {
        $today = CarbonImmutable::today();

        Cache::forget('dashboard:wallet-stats:'.$today->format('Y-m'));
        Cache::forget('dashboard:finance-stats:'.$today->toDateString());
    }
}
