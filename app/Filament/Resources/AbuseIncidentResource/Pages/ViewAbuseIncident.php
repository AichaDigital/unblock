<?php

declare(strict_types=1);

namespace App\Filament\Resources\AbuseIncidentResource\Pages;

use App\Filament\Resources\AbuseIncidentResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewAbuseIncident extends ViewRecord
{
    protected static string $resource = AbuseIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resolve')
                ->label('Mark as Resolved')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->resolve();

                    Notification::make()
                        ->title('Incident resolved')
                        ->success()
                        ->send();

                    $this->refreshFormData(['resolved_at']);
                })
                ->visible(fn () => ! $this->record->isResolved()),

            Action::make('unresolve')
                ->label('Mark as Unresolved')
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['resolved_at' => null]);

                    Notification::make()
                        ->title('Incident marked as unresolved')
                        ->warning()
                        ->send();

                    $this->refreshFormData(['resolved_at']);
                })
                ->visible(fn () => $this->record->isResolved()),
        ];
    }
}
