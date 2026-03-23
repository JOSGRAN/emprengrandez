<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use App\Services\PurchaseService;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(PurchaseService::class)->createPurchase([
                'supplier_name' => $data['supplier_name'] ?? null,
                'purchased_on' => CarbonImmutable::parse($data['purchased_on'])->toDateString(),
                'status' => $data['status'] ?? 'paid',
                'wallet_id' => $data['wallet_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'items' => $data['items'] ?? [],
                'created_by' => auth()->id(),
            ]);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            $field = str_contains($e->getMessage(), 'billetera') ? 'wallet_id' : 'items';

            throw ValidationException::withMessages([
                $field => $e->getMessage(),
            ]);
        }
    }
}
