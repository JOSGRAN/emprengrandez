<?php

namespace App\Services;

use App\Models\Credit;
use App\Models\Installment;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function recordPayment(
        Credit $credit,
        string $amount,
        CarbonImmutable $paidOn,
        ?Installment $installment = null,
        array $meta = [],
    ): Payment {
        return DB::transaction(function () use ($credit, $amount, $paidOn, $installment, $meta) {
            $amountCents = CreditService::toCents($amount);
            if ($amountCents <= 0) {
                throw new \InvalidArgumentException('El monto debe ser mayor a 0.');
            }

            $openInstallments = $credit->installments()
                ->where('status', '!=', 'paid')
                ->orderBy('due_date')
                ->orderBy('number')
                ->lockForUpdate()
                ->get();

            $remainingCents = (int) $openInstallments->sum(function (Installment $i) {
                $total = CreditService::toCents((string) $i->total_amount);
                $paid = CreditService::toCents((string) $i->paid_amount);

                return max(0, $total - $paid);
            });

            if ($amountCents > $remainingCents) {
                throw new \RuntimeException('El pago excede el saldo pendiente del crédito.');
            }

            $payment = new Payment;
            $payment->customer_id = $credit->customer_id;
            $payment->credit_id = $credit->id;
            $payment->installment_id = $installment?->id;
            $payment->wallet_id = $meta['wallet_id'] ?? app(WalletService::class)->getDefaultWalletId();
            $payment->paid_on = $paidOn->toDateString();
            $payment->amount = $amount;
            $payment->method = $meta['method'] ?? 'cash';
            $payment->reference = $meta['reference'] ?? null;
            $payment->notes = $meta['notes'] ?? null;
            $payment->status = $meta['status'] ?? 'posted';
            $payment->created_by = $meta['created_by'] ?? null;
            $payment->save();

            if ($installment) {
                $amountCents = $this->applyAmountToInstallment($installment, $amountCents, $paidOn);
            }

            if ($amountCents > 0) {
                foreach ($openInstallments as $target) {
                    if ($amountCents <= 0) {
                        break;
                    }

                    $amountCents = $this->applyAmountToInstallment($target, $amountCents, $paidOn);
                }
            }

            $this->recalculateCreditBalance($credit);

            app(NotificationService::class)->queuePaymentReceived($payment->refresh());

            return $payment->refresh();
        });
    }

    public function markOverdueInstallments(Credit $credit, CarbonImmutable $today): int
    {
        return $credit->installments()
            ->where('status', 'pending')
            ->whereDate('due_date', '<', $today->toDateString())
            ->update(['status' => 'overdue']);
    }

    public function recalculateCreditBalance(Credit $credit): void
    {
        $credit = $credit->refresh();

        $balanceCents = (int) $credit->installments()
            ->where('status', '!=', 'paid')
            ->get()
            ->sum(function (Installment $i) {
                $total = CreditService::toCents((string) $i->total_amount);
                $paid = CreditService::toCents((string) $i->paid_amount);

                return max(0, $total - $paid);
            });

        $credit->balance = CreditService::fromCents($balanceCents);
        if ($balanceCents <= 0) {
            $credit->status = 'closed';
        }
        $credit->save();
    }

    private function applyAmountToInstallment(Installment $installment, int $amountCents, CarbonImmutable $paidOn): int
    {
        $installment->refresh();

        if ($installment->status === 'paid') {
            return $amountCents;
        }

        $totalCents = CreditService::toCents((string) $installment->total_amount);
        $paidCents = CreditService::toCents((string) $installment->paid_amount);
        $remainingCents = max(0, $totalCents - $paidCents);

        if ($remainingCents <= 0) {
            $installment->status = 'paid';
            $installment->paid_at = $paidOn->toDateTimeString();
            $installment->save();

            return $amountCents;
        }

        $applyCents = min($remainingCents, $amountCents);
        $newPaidCents = $paidCents + $applyCents;

        $installment->paid_amount = CreditService::fromCents($newPaidCents);
        if ($newPaidCents >= $totalCents) {
            $installment->status = 'paid';
            $installment->paid_at = $paidOn->toDateTimeString();
        } else {
            $installment->status = $installment->status === 'overdue' ? 'overdue' : 'pending';
        }
        $installment->save();

        return $amountCents - $applyCents;
    }
}
