<?php

declare(strict_types=1);

namespace App\Filament\Resources\IpReputationResource\Pages;

use App\Filament\Resources\IpReputationResource;
use Filament\Resources\Pages\ListRecords;

class ListIpReputations extends ListRecords
{
    protected static string $resource = IpReputationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - records are created automatically by events
        ];
    }
}
