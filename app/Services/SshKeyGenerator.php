<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Host;
use Symfony\Component\Process\Process;

class SshKeyGenerator
{
    /**
     * Generate SSH keys for a host
     *
     * @return array{success: bool, message: string, public_key?: string, error?: string}
     */
    public function generateForHost(Host $host): array
    {
        $hostname = gethostname();
        $whoami = get_current_user();
        $keyName = "id_ed25519_{$hostname}_{$host->id}";

        // Use temporary directory inside the project
        $tempDir = base_path('storage/app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $keyPath = $tempDir.'/'.$keyName;

        // Generate SSH keys using ed25519 format
        $command = sprintf(
            'ssh-keygen -t ed25519 -a 100 -C "%s@%s" -f %s -N ""',
            $whoami,
            $hostname,
            escapeshellarg($keyPath)
        );

        $process = Process::fromShellCommandline($command);
        $process->run();

        if (! $process->isSuccessful()) {
            return [
                'success' => false,
                'message' => 'Failed to generate SSH keys',
                'error' => $process->getErrorOutput(),
            ];
        }

        // Read generated keys
        $privateKey = file_get_contents($keyPath);
        $publicKey = file_get_contents($keyPath.'.pub');

        if (! $privateKey || ! $publicKey) {
            return [
                'success' => false,
                'message' => 'Failed to read generated keys',
            ];
        }

        // Update host in database
        $host->update([
            'hash' => $privateKey,
            'hash_public' => $publicKey,
        ]);

        // Clean up temporary files
        if (file_exists($keyPath)) {
            unlink($keyPath);
        }
        if (file_exists($keyPath.'.pub')) {
            unlink($keyPath.'.pub');
        }

        return [
            'success' => true,
            'message' => 'SSH keys generated successfully',
            'public_key' => $publicKey,
        ];
    }

    /**
     * Check if host has SSH keys configured
     */
    public function hasKeys(Host $host): bool
    {
        return ! empty($host->hash) && ! empty($host->hash_public);
    }
}
