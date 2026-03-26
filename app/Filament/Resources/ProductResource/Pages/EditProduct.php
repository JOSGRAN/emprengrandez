<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Services\ImageService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected ?string $previousImagePath = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->previousImagePath = isset($data['image_path']) ? (string) $data['image_path'] : null;

        return $data;
    }

    protected function afterSave(): void
    {
        $new = (string) ($this->getRecord()->image_path ?? '');
        $old = trim((string) ($this->previousImagePath ?? ''));

        if ($old !== '' && $old !== $new) {
            app(ImageService::class)->deletePublicFile($old);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
