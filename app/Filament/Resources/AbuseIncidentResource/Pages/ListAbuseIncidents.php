<?php

declare(strict_types=1);

namespace App\Filament\Resources\AbuseIncidentResource\Pages;

use App\Filament\Resources\AbuseIncidentResource;
use Filament\Resources\Pages\ListRecords;

class ListAbuseIncidents extends ListRecords
{
    protected static string $resource = AbuseIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - records are created automatically by events
        ];
    }
}
