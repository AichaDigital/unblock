<?php

declare(strict_types=1);

namespace App\Console\Commands\Develop;

use App\Models\Host;
use App\Services\SshConnectionManager;
use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;

/**
 * Debug SSH Command Executor
 *
 * Ejecuta comandos SSH directamente en servidores remotos para debugging.
 * Solo disponible en modo desarrollo.
 */
class ExecuteRemoteSshCommand extends Command
{
    protected $signature = 'develop:ssh-exec
                            {host-id : ID del host a conectar}
                            {ssh-command : Comando SSH a ejecutar (entrecomillado)}
                            {--timeout=30 : Timeout en segundos}
                            {--force : No pedir confirmación}';

    protected $description = 'Execute SSH command on remote host for debugging (DEVELOPMENT ONLY)';

    public function handle(): int
    {
        // Security: Only in development
        if (app()->environment('production')) {
            $this->error('⛔ This command is disabled in production for security reasons.');

            return self::FAILURE;
        }

        $hostId = (int) $this->argument('host-id');
        $command = (string) $this->argument('ssh-command');
        $timeout = (int) $this->option('timeout');

        $this->info('🔧 Debug SSH Executor');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Load host
        $host = Host::find($hostId);

        if (! $host) {
            $this->error("❌ Host #{$hostId} not found");

            return self::FAILURE;
        }

        $this->table(
            ['Property', 'Value'],
            [
                ['ID', $host->id],
                ['FQDN', $host->fqdn],
                ['Panel', $host->panel->value],
                ['SSH Port', $host->port_ssh ?? 22],
            ]
        );

        $this->newLine();
        $this->warn('📡 Executing command:');
        $this->line("   {$command}");
        $this->newLine();

        // Confirm execution (unless --force)
        if (! $this->option('force') && ! $this->confirm('Execute this command?', true)) {
            $this->info('Aborted by user');

            return self::SUCCESS;
        }

        try {
            // Generate SSH key file
            $sshManager = app(SshConnectionManager::class);
            $keyPath = $sshManager->generateSshKey($host->hash); // Pass hash string

            try {
                // Create SSH connection
                $ssh = Ssh::create('root', $host->fqdn, $host->port_ssh ?? 22)
                    ->usePrivateKey($keyPath) // CORRECTED: use PATH
                    ->disableStrictHostKeyChecking()
                    ->setTimeout($timeout);

                $this->info("⏳ Connecting to {$host->fqdn}:{$host->port_ssh}...");

                $process = $ssh->execute($command);

                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $exitCode = $process->getExitCode();

                $this->newLine();
                $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                $this->info('📊 RESULT:');
                $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

                if (! empty($output)) {
                    $this->line('📤 STDOUT:');
                    $this->line($output);
                }

                if (! empty($errorOutput)) {
                    $this->newLine();
                    $this->warn('⚠️  STDERR:');
                    $this->line($errorOutput);
                }

                $this->newLine();
                $this->line("Exit Code: {$exitCode}");

                if ($process->isSuccessful()) {
                    $this->info('✅ Command executed successfully');

                    return self::SUCCESS;
                } else {
                    $this->error("❌ Command failed with exit code {$exitCode}");

                    return self::FAILURE;
                }
            } finally {
                // Always cleanup SSH key
                $sshManager->removeSshKey($keyPath);
            }

        } catch (\Throwable $e) {
            $this->newLine();
            $this->error("💥 EXCEPTION: {$e->getMessage()}");
            $this->line("File: {$e->getFile()}:{$e->getLine()}");

            if ($this->getOutput()->isVerbose()) {
                $this->newLine();
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }
}
