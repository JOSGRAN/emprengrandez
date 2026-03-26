<?php

namespace App\Services;

use App\Models\Credit;
use App\Models\CreditItem;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\ProductVariant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreditService
{
    public function createCredit(Customer $customer, array $data): Credit
    {
        return DB::transaction(function () use ($customer, $data) {
            $installmentsCount = (int) ($data['installments_count'] ?? 0);
            $items = $data['items'] ?? [];

            if (! is_array($items) || count($items) === 0) {
                throw new \InvalidArgumentException('Debe agregar al menos un producto.');
            }

            $principalCents = 0;
            foreach ($items as $item) {
                $quantity = (int) ($item['quantity'] ?? 0);
                $priceCents = self::toCents((string) ($item['price'] ?? 0));

                if ($quantity < 1) {
                    throw new \InvalidArgumentException('La cantidad debe ser mayor a 0.');
                }

                if ($priceCents <= 0) {
                    throw new \InvalidArgumentException('El precio debe ser mayor a 0.');
                }

                $principalCents += ($priceCents * $quantity);
            }

            if ($principalCents <= 0) {
                throw new \InvalidArgumentException('El monto debe ser mayor a 0.');
            }

            if ($installmentsCount < 1 || $installmentsCount > 365) {
                throw new \InvalidArgumentException('El número de cuotas debe estar entre 1 y 365.');
            }

            if ($customer->hasOverdueInstallments()) {
                throw new \RuntimeException('Cliente con deuda vencida. No se puede crear un nuevo crédito.');
            }

            $credit = new Credit;
            $credit->customer()->associate($customer);
            $credit->start_date = $data['start_date'];
            $credit->principal_amount = self::fromCents($principalCents);
            $credit->interest_type = $data['interest_type'] ?? 'none';
            $credit->interest_rate = $data['interest_rate'] ?? 0;
            $credit->calculation_method = $data['calculation_method'] ?? 'direct';
            $credit->frequency = $data['frequency'] ?? 'monthly';
            $credit->installments_count = $data['installments_count'];
            $credit->status = $data['status'] ?? 'active';
            $credit->created_by = $data['created_by'] ?? null;
            $credit->updated_by = $data['updated_by'] ?? null;

            if ($credit->interest_type === 'none') {
                $credit->interest_rate = 0;
                $credit->calculation_method = 'direct';
            }

            $credit->save();

            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $variantId = (int) ($item['product_variant_id'] ?? 0);
                $quantity = (int) ($item['quantity'] ?? 0);
                $priceCents = self::toCents((string) ($item['price'] ?? 0));
                $totalCents = $priceCents * $quantity;

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

                $creditItem = new CreditItem;
                $creditItem->credit()->associate($credit);
                $creditItem->product_id = $productId;
                $creditItem->product_variant_id = $variantId;
                $creditItem->quantity = $quantity;
                $creditItem->price = self::fromCents($priceCents);
                $creditItem->total = self::fromCents($totalCents);
                $creditItem->save();
            }

            $schedule = $this->generateSchedule(
                principalAmount: (string) $credit->principal_amount,
                installmentsCount: (int) $credit->installments_count,
                frequency: (string) $credit->frequency,
                interestType: (string) $credit->interest_type,
                interestRate: (string) $credit->interest_rate,
                calculationMethod: (string) $credit->calculation_method,
                startDate: CarbonImmutable::parse($credit->start_date),
            );

            $totalInterestCents = 0;
            $totalAmountCents = 0;

            foreach ($schedule as $row) {
                $installment = new Installment;
                $installment->credit()->associate($credit);
                $installment->number = $row['number'];
                $installment->due_date = $row['due_date'];
                $installment->principal_amount = $row['principal_amount'];
                $installment->interest_amount = $row['interest_amount'];
                $installment->total_amount = $row['total_amount'];
                $installment->status = 'pending';
                $installment->save();

                $totalInterestCents += self::toCents($row['interest_amount']);
                $totalAmountCents += self::toCents($row['total_amount']);
            }

            $credit->total_interest = self::fromCents($totalInterestCents);
            $credit->total_amount = self::fromCents($totalAmountCents);
            $credit->balance = $credit->total_amount;
            $credit->save();

            app(NotificationService::class)->queueCreditCreated($credit->refresh());

            return $credit->refresh();
        });
    }

    public function cancelCredit(Credit $credit, string $reason, ?int $cancelledBy = null): Credit
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Debe ingresar un motivo de cancelación.');
        }

        return DB::transaction(function () use ($credit, $reason, $cancelledBy) {
            $credit = Credit::query()->lockForUpdate()->findOrFail($credit->id);

            if ($credit->status === 'cancelled') {
                return $credit->refresh();
            }

            $hasPostedPayments = Payment::query()
                ->where('credit_id', $credit->id)
                ->where('status', 'posted')
                ->exists();

            if ($hasPostedPayments) {
                throw new \RuntimeException('No se puede cancelar el crédito porque existen pagos registrados.');
            }

            $items = CreditItem::query()
                ->where('credit_id', $credit->id)
                ->orderBy('id')
                ->get();

            foreach ($items as $item) {
                $variantId = (int) ($item->product_variant_id ?? 0);
                $qty = (int) ($item->quantity ?? 0);
                if ($variantId <= 0 || $qty <= 0) {
                    continue;
                }

                $variant = ProductVariant::query()
                    ->lockForUpdate()
                    ->find($variantId);

                if ($variant) {
                    $variant->increment('stock', $qty);
                }
            }

            Installment::query()
                ->where('credit_id', $credit->id)
                ->delete();

            $credit->status = 'cancelled';
            $credit->balance = 0;
            $credit->cancelled_at = now();
            $credit->cancelled_by = $cancelledBy;
            $credit->cancel_reason = $reason;
            $credit->save();

            return $credit->refresh();
        });
    }

    /**
     * @return array<int, array{number:int, due_date:string, principal_amount:string, interest_amount:string, total_amount:string}>
     */
    public function generateSchedule(
        string $principalAmount,
        int $installmentsCount,
        string $frequency,
        string $interestType,
        string $interestRate,
        string $calculationMethod,
        CarbonImmutable $startDate,
    ): array {
        $n = max(1, $installmentsCount);
        $principalCents = self::toCents($principalAmount);
        $rate = (float) $interestRate;

        if ($interestType === 'none' || $rate <= 0) {
            return $this->scheduleNoInterest($principalCents, $n, $frequency, $startDate);
        }

        if ($interestType === 'simple') {
            return $this->scheduleSimpleInterest($principalCents, $n, $frequency, $startDate, $rate);
        }

        return $this->scheduleCompoundInterest($principalCents, $n, $frequency, $startDate, $rate, $calculationMethod);
    }

    private function scheduleNoInterest(int $principalCents, int $n, string $frequency, CarbonImmutable $startDate): array
    {
        $principalPer = intdiv($principalCents, $n);
        $rows = [];
        $remainingPrincipal = $principalCents;

        for ($i = 1; $i <= $n; $i++) {
            $principalPart = $i < $n ? $principalPer : $remainingPrincipal;
            $remainingPrincipal -= $principalPart;

            $dueDate = $this->dueDateForInstallment($startDate, $frequency, $i);
            $rows[] = [
                'number' => $i,
                'due_date' => $dueDate->toDateString(),
                'principal_amount' => self::fromCents($principalPart),
                'interest_amount' => self::fromCents(0),
                'total_amount' => self::fromCents($principalPart),
            ];
        }

        return $rows;
    }

    private function scheduleSimpleInterest(int $principalCents, int $n, string $frequency, CarbonImmutable $startDate, float $rate): array
    {
        $principalPer = intdiv($principalCents, $n);
        $interestPer = (int) round($principalCents * $rate);
        $rows = [];
        $remainingPrincipal = $principalCents;

        for ($i = 1; $i <= $n; $i++) {
            $principalPart = $i < $n ? $principalPer : $remainingPrincipal;
            $remainingPrincipal -= $principalPart;

            $dueDate = $this->dueDateForInstallment($startDate, $frequency, $i);
            $total = $principalPart + $interestPer;

            $rows[] = [
                'number' => $i,
                'due_date' => $dueDate->toDateString(),
                'principal_amount' => self::fromCents($principalPart),
                'interest_amount' => self::fromCents($interestPer),
                'total_amount' => self::fromCents($total),
            ];
        }

        return $rows;
    }

    private function scheduleCompoundInterest(int $principalCents, int $n, string $frequency, CarbonImmutable $startDate, float $rate, string $method): array
    {
        $rows = [];
        $balance = $principalCents;

        $method = match ($method) {
            'french', 'german' => $method,
            default => 'german',
        };

        $principalPer = $method === 'german' ? intdiv($principalCents, $n) : null;
        $paymentCents = $method === 'french'
            ? (int) round($principalCents * ($rate / (1 - pow(1 + $rate, -$n))))
            : null;

        for ($i = 1; $i <= $n; $i++) {
            $interest = (int) round($balance * $rate);

            if ($method === 'french') {
                $principalPart = $i < $n ? max(0, $paymentCents - $interest) : $balance;
            } else {
                $principalPart = $i < $n ? $principalPer : $balance;
            }

            if ($principalPart > $balance) {
                $principalPart = $balance;
            }

            $balance -= $principalPart;

            $dueDate = $this->dueDateForInstallment($startDate, $frequency, $i);
            $rows[] = [
                'number' => $i,
                'due_date' => $dueDate->toDateString(),
                'principal_amount' => self::fromCents($principalPart),
                'interest_amount' => self::fromCents($interest),
                'total_amount' => self::fromCents($principalPart + $interest),
            ];
        }

        return $rows;
    }

    private function dueDateForInstallment(CarbonImmutable $startDate, string $frequency, int $number): CarbonImmutable
    {
        return match ($frequency) {
            'weekly' => $startDate->addWeeks($number),
            'biweekly' => $startDate->addWeeks($number * 2),
            default => $startDate->addMonthsNoOverflow($number),
        };
    }

    public static function toCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
