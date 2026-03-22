<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Models\Customer;
use App\Services\SaleService;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $customer = isset($data['customer_id']) && $data['customer_id']
            ? Customer::query()->findOrFail($data['customer_id'])
            : null;

        try {
            return app(SaleService::class)->createSale($customer, [
                'sold_on' => CarbonImmutable::parse($data['sold_on'])->toDateString(),
                'wallet_id' => $data['wallet_id'] ?? null,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'status' => $data['status'] ?? 'posted',
                'notes' => $data['notes'] ?? null,
                'items' => $data['items'] ?? [],
                'created_by' => auth()->id(),
            ]);
        } catch (\RuntimeException $e) {
            $field = str_contains($e->getMessage(), 'Stock') ? 'items' : 'total';

            throw ValidationException::withMessages([
                $field => $e->getMessage(),
            ]);
        }
    }
}
