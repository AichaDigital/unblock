<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailReputationResource\Pages;

use App\Filament\Resources\EmailReputationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEmailReputation extends ViewRecord
{
    protected static string $resource = EmailReputationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
