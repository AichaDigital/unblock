<?php

declare(strict_types=1);

namespace App\Filament\Resources\IpReputationResource\Pages;

use App\Filament\Resources\IpReputationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewIpReputation extends ViewRecord
{
    protected static string $resource = IpReputationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
