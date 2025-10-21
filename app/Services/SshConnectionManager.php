<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\{ConnectionFailedException};
use App\Models\Host;
use Illuminate\Support\Facades\{File, Log, Storage};
use Illuminate\Support\Str;
use Spatie\Ssh\Ssh;

/**
 * SSH Connection Manager - Single Responsibility Pattern
 *
 * Handles all SSH connection operations including:
 * - SSH key generation and management
 * - SSH connection establishment
 * - Command execution via SSH
 * - Connection cleanup
 */
class SshConnectionManager
{
    /**
     * Generate a temporary SSH key for server connection
     */
    public function generateSshKey(string $hash): string
    {
        $filename = 'key_'.Str::random(10);
        $path = storage_path('app/.ssh/'.$filename);

        // Normalize line endings and ensure final newline
        $normalizedHash = str_replace(["\r\n", "\r"], "\n", $hash);
        if (! str_ends_with($normalizedHash, "\n")) {
            $normalizedHash .= "\n";
        }

        Storage::disk('ssh')->put($filename, $normalizedHash);

        return $path;
    }

    /**
     * Prepare multiplexing path for SSH connections
     */
    public function prepareMultiplexingPath(): void
    {
        $directoryPath = '/tmp/cm';

        if (! File::exists($directoryPath)) {
            if (! File::makeDirectory($directoryPath, 0755, true)) {
                Log::error('Error creating SSH multiplexing directory');
                throw new ConnectionFailedException(
                    'Could not create SSH multiplexing directory: '.$directoryPath
                );
            }
        }
    }

    /**
     * Execute a command on a remote host via SSH
     */
    public function executeCommand(Host $host, string $sshKeyPath, string $command): string
    {
        try {
            $port = $host->port_ssh ?? 22;
            $controlPath = '/tmp/cm/ssh_mux_%h_%p_%r';

            $ssh = Ssh::create('root', $host->fqdn, $port)
                ->usePrivateKey($sshKeyPath)
                ->configureProcess(function ($process) use ($controlPath) {
                    // Configurar opciones de multiplexing SSH
                    $process->setEnv([
                        'SSH_MULTIPLEX_OPTIONS' => "-o ControlMaster=auto -o ControlPath=$controlPath -o ControlPersist=60s",
                    ]);

                    return $process;
                });

            $process = $ssh->execute($command);
            $output = $process->getOutput();

            if ($process->getExitCode() !== 0) {
                Log::warning('SSH command execution returned non-zero exit code', [
                    'host' => $host->fqdn,
                    'command' => $command,
                    'exit_code' => $process->getExitCode(),
                    'error_output' => $process->getErrorOutput(),
                ]);
            }

            return trim($output);

        } catch (\Exception $e) {
            $msg = __('messages.firewall.errors.ssh_connection', [
                'unity' => 'command_execution',
                'server' => $host->fqdn,
                'error' => $e->getMessage(),
            ]);

            Log::error($msg, [
                'host_id' => $host->id,
                'host_fqdn' => $host->fqdn,
                'command' => $command,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new ConnectionFailedException($msg, $host->fqdn, $host->port_ssh ?? 22, previous: $e);
        }
    }

    /**
     * Clean up temporary SSH key file
     */
    public function removeSshKey(string $keyPath): void
    {
        if (file_exists($keyPath)) {
            Storage::disk('ssh')->delete(basename($keyPath));
        }
    }

    /**
     * Create SSH connection session manager for multiple commands
     */
    public function createSession(Host $host): SshSession
    {
        $sshKeyPath = $this->generateSshKey($host->hash);
        $this->prepareMultiplexingPath();

        return new SshSession($this, $host, $sshKeyPath);
    }
}
