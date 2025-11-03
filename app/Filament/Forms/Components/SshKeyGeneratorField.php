<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Services\SshKeyGenerator;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\{Component};
use Filament\Forms\{Get, Set};
use Filament\Notifications\Notification;

class SshKeyGeneratorField extends Component
{
    protected string $view = 'filament.forms.components.ssh-key-generator';

    public static function make(string $name = 'ssh_key_generator'): static
    {
        return app(static::class, ['name' => $name]);
    }

    public function generateAction(): Action
    {
        return Action::make('generate')
            ->label(__('hosts.ssh_keys.generate'))
            ->icon('heroicon-o-key')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading(__('hosts.ssh_keys.generate_confirm_title'))
            ->modalDescription(__('hosts.ssh_keys.generate_confirm_description'))
            ->modalSubmitActionLabel(__('hosts.ssh_keys.generate_confirm'))
            ->action(function (Set $set, Get $get) {
                try {
                    $fqdn = $get('fqdn');

                    if (! $fqdn) {
                        Notification::make()
                            ->title(__('hosts.ssh_keys.fqdn_required'))
                            ->danger()
                            ->send();

                        return;
                    }

                    $generator = app(SshKeyGenerator::class);
                    $keys = $generator->generateForFqdn($fqdn);

                    $set('hash', $keys['private']);
                    $set('hash_public', $keys['public']);

                    Notification::make()
                        ->title(__('hosts.ssh_keys.generated_success'))
                        ->body(__('hosts.ssh_keys.generated_success_body'))
                        ->success()
                        ->send();

                } catch (\Exception $e) {
                    Notification::make()
                        ->title(__('hosts.ssh_keys.generation_failed'))
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
