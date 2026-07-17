<?php

namespace App\Filament\Resources\PatternDetectionResource\Pages;

use App\Filament\Resources\PatternDetectionResource;
use Filament\Resources\Pages\ListRecords;

class ListPatternDetections extends ListRecords
{
    protected static string $resource = PatternDetectionResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
