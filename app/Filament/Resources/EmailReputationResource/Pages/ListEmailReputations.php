<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailReputationResource\Pages;

use App\Filament\Resources\EmailReputationResource;
use Filament\Resources\Pages\ListRecords;

class ListEmailReputations extends ListRecords
{
    protected static string $resource = EmailReputationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - records are created automatically by events
        ];
    }
}
