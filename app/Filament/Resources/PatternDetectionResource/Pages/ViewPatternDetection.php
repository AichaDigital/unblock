<?php

namespace App\Filament\Resources\PatternDetectionResource\Pages;

use App\Filament\Resources\PatternDetectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPatternDetection extends ViewRecord
{
    protected static string $resource = PatternDetectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resolve')
                ->label('Mark as Resolved')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => ! $this->record->isResolved())
                ->requiresConfirmation()
                ->action(fn () => $this->record->resolve())
                ->after(fn () => $this->redirect($this->getResource()::getUrl('index'))),

            Actions\Action::make('unresolve')
                ->label('Reopen')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => $this->record->isResolved())
                ->requiresConfirmation()
                ->action(fn () => $this->record->update(['resolved_at' => null]))
                ->after(fn () => $this->redirect($this->getResource()::getUrl('index'))),
        ];
    }
}
