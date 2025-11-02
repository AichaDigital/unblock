<?php

namespace App\Filament\Resources\HostResource\Pages;

use App\Filament\Actions\GenerateSshKeysAction;
use App\Filament\Resources\HostResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewHost extends ViewRecord
{
    protected static string $resource = HostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GenerateSshKeysAction::make(),
            EditAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Make SSH keys visible for viewing
        $record = $this->getRecord();
        $data['hash'] = $record->hash;
        $data['hash_public'] = $record->hash_public;

        return $data;
    }
}
