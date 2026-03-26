<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Services\ImageService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    protected ?string $previousAttachmentPath = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->previousAttachmentPath = isset($data['attachment_path']) ? (string) $data['attachment_path'] : null;

        return $data;
    }

    protected function afterSave(): void
    {
        $new = (string) ($this->getRecord()->attachment_path ?? '');
        $old = trim((string) ($this->previousAttachmentPath ?? ''));

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
