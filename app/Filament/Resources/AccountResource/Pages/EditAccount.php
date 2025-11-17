<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Filament\Resources\AccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAccount extends EditRecord
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('toggle_suspension')
                ->label(fn () => $this->record->suspended_at ? __('messages.accounts.unsuspend') : __('messages.accounts.suspend'))
                ->icon(fn () => $this->record->suspended_at ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                ->color(fn () => $this->record->suspended_at ? 'success' : 'danger')
                ->requiresConfirmation()
                ->modalHeading(fn () => $this->record->suspended_at ? __('messages.accounts.unsuspend_account') : __('messages.accounts.suspend_account'))
                ->modalDescription(fn () => $this->record->suspended_at
                    ? __('messages.accounts.unsuspend_confirmation')
                    : __('messages.accounts.suspend_confirmation'))
                ->action(function () {
                    if ($this->record->suspended_at) {
                        $this->record->update(['suspended_at' => null]);
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title(__('messages.accounts.account_unsuspended'))
                            ->body(__('messages.accounts.account_unsuspended_success', ['username' => $this->record->username]))
                            ->send();
                    } else {
                        $this->record->update(['suspended_at' => now()]);
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title(__('messages.accounts.account_suspended'))
                            ->body(__('messages.accounts.account_suspended_success', ['username' => $this->record->username]))
                            ->send();
                    }
                }),
            Actions\ViewAction::make(),
        ];
    }
}
