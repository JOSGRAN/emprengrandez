<?php

namespace App\Filament\Resources\CreditResource\Pages;

use App\Filament\Resources\CreditResource;
use App\Models\Customer;
use App\Services\CreditService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateCredit extends CreateRecord
{
    protected static string $resource = CreditResource::class;

    public function mount(): void
    {
        parent::mount();

        $customerId = request()->query('customer_id');
        if ($customerId) {
            $this->form->fill([
                'customer_id' => (int) $customerId,
                'start_date' => now()->toDateString(),
                'frequency' => 'monthly',
                'interest_type' => 'none',
                'calculation_method' => 'direct',
                'status' => 'active',
            ]);
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        $customer = Customer::query()->findOrFail($data['customer_id']);

        try {
            return app(CreditService::class)->createCredit($customer, [
                'start_date' => $data['start_date'],
                'principal_amount' => $data['principal_amount'],
                'interest_type' => $data['interest_type'] ?? 'none',
                'interest_rate' => $data['interest_rate'] ?? 0,
                'calculation_method' => $data['calculation_method'] ?? 'direct',
                'frequency' => $data['frequency'] ?? 'monthly',
                'installments_count' => $data['installments_count'],
                'status' => $data['status'] ?? 'active',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'customer_id' => $e->getMessage(),
            ]);
        }
    }
}
