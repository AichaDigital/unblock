<?php

declare(strict_types=1);

namespace App\Filament\Resources\HostResource\Pages;

use App\Filament\Actions\{GenerateSshKeysAction, TestHostConnectionAction};
use App\Filament\Resources\HostResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHost extends EditRecord
{
    protected static string $resource = HostResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            TestHostConnectionAction::make(),
            GenerateSshKeysAction::make(),
            DeleteAction::make(),
        ];
    }

    #[\Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Make SSH keys visible for Filament forms
        $record = $this->getRecord();
        $data['hash'] = $record->hash;
        $data['hash_public'] = $record->hash_public;

        return $data;
    }
}
