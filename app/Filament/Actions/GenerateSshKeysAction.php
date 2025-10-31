<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Models\Host;
use App\Services\SshKeyGenerator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class GenerateSshKeysAction
{
    public static function make(): Action
    {
        return Action::make('generateSshKeys')
            ->label(__('hosts.ssh_keys.generate_action'))
            ->icon('heroicon-o-key')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('hosts.ssh_keys.generate_confirmation_title'))
            ->modalDescription(__('hosts.ssh_keys.generate_confirmation_description'))
            ->modalSubmitActionLabel(__('hosts.ssh_keys.generate_confirmation_submit'))
            ->action(function (Host $record, $livewire) {
                $generator = new SshKeyGenerator;
                $result = $generator->generateForHost($record);

                if ($result['success']) {
                    // Refresh the record to get the newly generated keys
                    $record->refresh();

                    // Update the form data in the livewire component
                    $livewire->data['hash'] = $record->hash;
                    $livewire->data['hash_public'] = $record->hash_public;

                    Notification::make()
                        ->success()
                        ->title(__('hosts.ssh_keys.generate_success_title'))
                        ->body(__('hosts.ssh_keys.generate_success_body', ['public_key' => $result['public_key']]))
                        ->persistent()
                        ->send();
                } else {
                    $errorMessage = $result['message'];
                    if (isset($result['error'])) {
                        $errorMessage .= ': '.$result['error'];
                    }

                    Notification::make()
                        ->danger()
                        ->title(__('hosts.ssh_keys.generate_error_title'))
                        ->body(__('hosts.ssh_keys.generate_error_body', ['message' => $errorMessage]))
                        ->persistent()
                        ->send();
                }
            });
    }
}
