<?php

namespace App\Filament\Resources\SettingResource\Pages;

use App\Filament\Resources\SettingResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Cache;

class CreateSetting extends CreateRecord
{
    protected static string $resource = SettingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $type = $data['type'] ?? 'string';

        $data['value'] = match ($type) {
            'int' => isset($data['value_int']) ? (int) $data['value_int'] : null,
            'bool' => (bool) ($data['value_bool'] ?? false),
            default => (string) ($data['value_string'] ?? ''),
        };

        unset($data['value_string'], $data['value_int'], $data['value_bool']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        Cache::forget('settings:'.$record->key);
    }
}
