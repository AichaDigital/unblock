<?php

namespace App\Filament\Resources\HostResource\Pages;

use App\Filament\Resources\HostResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHost extends CreateRecord
{
    protected static string $resource = HostResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
