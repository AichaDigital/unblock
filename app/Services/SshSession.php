<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Host;
use Illuminate\Support\Facades\Log;

/**
 * SSH Session - Manages a single SSH session with automatic cleanup
 */
class SshSession
{
    public function __construct(
        private SshConnectionManager $connectionManager,
        private Host $host,
        private string $sshKeyPath
    ) {}

    /**
     * Execute a command in this SSH session with comprehensive logging
     */
    public function execute(string $command): string
    {
        $startTime = microtime(true);

        Log::info('SSH Command: Starting execution', [
            'host' => $this->host->fqdn,
            'command' => $command,
            'ssh_key_path' => $this->sshKeyPath,
            'session_id' => spl_object_hash($this),
        ]);

        try {
            $output = $this->connectionManager->executeCommand($this->host, $this->sshKeyPath, $command);
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('SSH Command: Execution completed successfully', [
                'host' => $this->host->fqdn,
                'command' => $command,
                'execution_time_ms' => $executionTime,
                'output_length' => strlen($output),
                'output_preview' => substr($output, 0, 200).(strlen($output) > 200 ? '...' : ''),
                'session_id' => spl_object_hash($this),
            ]);

            return $output;

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('SSH Command: Execution failed', [
                'host' => $this->host->fqdn,
                'command' => $command,
                'execution_time_ms' => $executionTime,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'session_id' => spl_object_hash($this),
            ]);

            throw $e;
        }
    }

    /**
     * Get the SSH key path for this session
     */
    public function getSshKeyPath(): string
    {
        return $this->sshKeyPath;
    }

    /**
     * Get the host for this session
     */
    public function getHost(): Host
    {
        return $this->host;
    }

    /**
     * Clean up SSH session resources
     */
    public function cleanup(): void
    {
        try {
            Log::debug('SSH Session: Cleaning up session', [
                'host' => $this->host->fqdn,
                'ssh_key_path' => $this->sshKeyPath,
                'session_id' => spl_object_hash($this),
            ]);
        } catch (\Throwable $e) {
            // Ignore logging errors during cleanup, as the app might be tearing down.
        }

        $this->connectionManager->removeSshKey($this->sshKeyPath);
    }

    /**
     * Auto-cleanup on destruction
     */
    public function __destruct()
    {
        $this->cleanup();
    }
}
