<?php

declare(strict_types=1);

namespace App\Filament\Resources\HostingResource\Pages;

use App\Filament\Resources\HostingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHosting extends CreateRecord
{
    protected static string $resource = HostingResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
