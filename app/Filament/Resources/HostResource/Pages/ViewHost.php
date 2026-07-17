<?php

declare(strict_types=1);

namespace App\Filament\Resources\HostResource\Pages;

use App\Filament\Actions\{GenerateSshKeysAction, TestHostConnectionAction};
use App\Filament\Resources\HostResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewHost extends ViewRecord
{
    protected static string $resource = HostResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            TestHostConnectionAction::make(),
            GenerateSshKeysAction::make(),
            EditAction::make(),
        ];
    }

    #[\Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Make SSH keys visible for viewing
        $record = $this->getRecord();
        $data['hash'] = $record->hash;
        $data['hash_public'] = $record->hash_public;

        return $data;
    }
}
