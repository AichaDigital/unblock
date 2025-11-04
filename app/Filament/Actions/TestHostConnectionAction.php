<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Models\Host;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\{Artisan, Log};

/**
 * Test Host Connection Action
 *
 * Tests SSH connection to a host using the develop:test-host-connection command
 */
class TestHostConnectionAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'test_connection';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('hosts.actions.test_connection'))
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading(__('hosts.actions.test_connection_modal_title'))
            ->modalDescription(__('hosts.actions.test_connection_modal_description'))
            ->modalSubmitActionLabel(__('hosts.actions.test_connection_submit'))
            ->action(function (Host $record): void {
                try {
                    // Execute the artisan command and capture output
                    $exitCode = Artisan::call('develop:test-host-connection', [
                        '--host-id' => $record->id,
                    ]);

                    $output = Artisan::output();

                    // Check if test was successful (exit code 0 AND contains success marker)
                    $isSuccess = $exitCode === 0 && (
                        str_contains($output, 'CONEXIÓN SSH EXITOSA') ||
                        str_contains($output, 'SSH connection successful')
                    );

                    if ($isSuccess) {
                        Notification::make()
                            ->title(__('hosts.notifications.test_success_title'))
                            ->body(__('hosts.notifications.test_success_body', [
                                'fqdn' => $record->fqdn,
                            ]))
                            ->success()
                            ->send();

                        Log::info('Host connection test successful', [
                            'host_id' => $record->id,
                            'fqdn' => $record->fqdn,
                        ]);
                    } else {
                        Notification::make()
                            ->title(__('hosts.notifications.test_failed_title'))
                            ->body(__('hosts.notifications.test_failed_body'))
                            ->danger()
                            ->send();

                        Log::warning('Host connection test failed', [
                            'host_id' => $record->id,
                            'fqdn' => $record->fqdn,
                            'exit_code' => $exitCode,
                            'output' => $output, // Log completo para admin
                        ]);
                    }
                } catch (\Exception $e) {
                    Notification::make()
                        ->title(__('hosts.notifications.test_error_title'))
                        ->body(__('hosts.notifications.test_error_body'))
                        ->danger()
                        ->send();

                    Log::error('Host connection test error', [
                        'host_id' => $record->id,
                        'fqdn' => $record->fqdn,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }
}
