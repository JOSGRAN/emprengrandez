<?php

namespace App\Filament\Resources\SettingResource\Pages;

use App\Filament\Resources\SettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Cache;

class EditSetting extends EditRecord
{
    protected static string $resource = SettingResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $value = $data['value'] ?? null;
        $type = $data['type'] ?? 'string';

        if ($type === 'bool') {
            $data['value_bool'] = (bool) $value;
        } elseif ($type === 'int') {
            $data['value_int'] = is_numeric($value) ? (int) $value : null;
        } else {
            $data['value_string'] = is_scalar($value) ? (string) $value : '';
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        Cache::forget('settings:'.$record->key);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
