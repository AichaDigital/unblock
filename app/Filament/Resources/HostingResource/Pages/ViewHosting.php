<?php

declare(strict_types=1);

namespace App\Filament\Resources\HostingResource\Pages;

use App\Filament\Resources\HostingResource;
use Filament\Actions\{DeleteAction, EditAction};
use Filament\Resources\Pages\ViewRecord;

class ViewHosting extends ViewRecord
{
    protected static string $resource = HostingResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
