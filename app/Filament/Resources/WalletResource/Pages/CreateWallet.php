<?php

namespace App\Filament\Resources\WalletResource\Pages;

use App\Filament\Resources\WalletResource;
use App\Models\Wallet;
use App\Services\WalletService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWallet extends CreateRecord
{
    protected static string $resource = WalletResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $balance = (string) ($data['balance'] ?? '0');
        $data['balance'] = 0;

        /** @var Wallet $wallet */
        $wallet = static::getModel()::query()->create($data);

        if ((float) $balance !== 0.0) {
            app(WalletService::class)->record(
                walletId: $wallet->id,
                type: 'income',
                amount: $balance,
                description: 'Saldo inicial',
                referenceType: 'manual',
                referenceId: $wallet->id,
                isReversal: false,
            );
        }

        return $wallet;
    }
}
