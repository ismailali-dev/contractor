<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\ReportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReport extends EditRecord
{
    protected static string $resource = ReportResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $status = (string) ($data['status'] ?? 'open');
        if ($status !== 'open') {
            if (blank($data['resolved_at'] ?? null)) {
                $data['resolved_at'] = now();
            }
            if (blank($data['resolved_by'] ?? null)) {
                $data['resolved_by'] = auth()->id();
            }
        } else {
            $data['resolved_at'] = null;
            $data['resolved_by'] = null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

