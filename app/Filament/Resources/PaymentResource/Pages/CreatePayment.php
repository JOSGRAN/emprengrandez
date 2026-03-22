<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Models\Credit;
use App\Models\Installment;
use App\Services\PaymentService;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    public function mount(): void
    {
        parent::mount();

        $creditId = request()->query('credit_id');
        $installmentId = request()->query('installment_id');
        $amount = request()->query('amount');

        if ($creditId) {
            $this->form->fill([
                'credit_id' => (int) $creditId,
                'installment_id' => $installmentId ? (int) $installmentId : null,
                'amount' => $amount !== null ? (string) $amount : null,
                'paid_on' => now()->toDateString(),
                'method' => 'cash',
            ]);
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        $credit = Credit::query()->findOrFail($data['credit_id']);
        $installment = isset($data['installment_id']) && $data['installment_id']
            ? Installment::query()->where('credit_id', $credit->id)->findOrFail($data['installment_id'])
            : null;

        try {
            return app(PaymentService::class)->recordPayment(
                credit: $credit,
                amount: (string) $data['amount'],
                paidOn: CarbonImmutable::parse($data['paid_on']),
                installment: $installment,
                meta: [
                    'wallet_id' => $data['wallet_id'] ?? null,
                    'method' => $data['method'] ?? 'cash',
                    'reference' => $data['reference'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'status' => 'posted',
                    'created_by' => auth()->id(),
                ],
            );
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'amount' => $e->getMessage(),
            ]);
        }
    }
}
