<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\{BfmWhitelistEntry, Host};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;
use Spatie\Ssh\Ssh;

/**
 * Remove expired IPs from DirectAdmin BFM whitelist
 *
 * This job runs periodically to remove IPs from the DirectAdmin BFM whitelist
 * that have exceeded their TTL period
 */
class RemoveExpiredBfmWhitelistIps implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting removal of expired BFM whitelist IPs');

        // Get all expired entries that haven't been removed yet
        $expiredEntries = BfmWhitelistEntry::expired()
            ->with('host')
            ->get();

        if ($expiredEntries->isEmpty()) {
            Log::info('No expired BFM whitelist entries found');

            return;
        }

        Log::info('Found expired BFM whitelist entries', [
            'count' => $expiredEntries->count(),
        ]);

        $removedCount = 0;
        $failedCount = 0;

        // Group by host to minimize SSH connections
        $entriesByHost = $expiredEntries->groupBy('host_id');

        foreach ($entriesByHost as $hostId => $entries) {
            $host = $entries->first()->host;

            if (! $host || $host->panel !== 'directadmin') {
                Log::warning('Skipping entries for non-DirectAdmin host', [
                    'host_id' => $hostId,
                ]);

                continue;
            }

            try {
                $this->removeEntriesFromHost($host, $entries);
                $removedCount += $entries->count();
            } catch (\Exception $e) {
                Log::error('Failed to remove expired entries from host', [
                    'host' => $host->fqdn,
                    'error' => $e->getMessage(),
                    'entries_count' => $entries->count(),
                ]);
                $failedCount += $entries->count();
            }
        }

        Log::info('Completed removal of expired BFM whitelist IPs', [
            'removed' => $removedCount,
            'failed' => $failedCount,
        ]);
    }

    /**
     * Remove expired entries from a specific host
     */
    private function removeEntriesFromHost(Host $host, $entries): void
    {
        Log::info('Removing expired entries from host', [
            'host' => $host->fqdn,
            'entries_count' => $entries->count(),
        ]);

        // Create SSH connection
        $ssh = Ssh::create($host->admin, $host->fqdn, $host->port_ssh ?? 22)
            ->usePrivateKey($this->generateTempSshKey($host->hash));

        try {
            // Get current whitelist content
            $whitelistPath = '/usr/local/directadmin/data/admin/ip_whitelist';
            $currentContent = $ssh->execute("cat {$whitelistPath} 2>/dev/null || echo ''");

            $lines = array_filter(array_map('trim', explode("\n", $currentContent)));
            $ipsToRemove = $entries->pluck('ip_address')->toArray();

            // Filter out expired IPs
            $newLines = array_filter($lines, function ($line) use ($ipsToRemove) {
                $ip = trim($line);

                return ! in_array($ip, $ipsToRemove);
            });

            // Write back the filtered content
            $newContent = implode("\n", $newLines);
            if (! empty($newContent)) {
                $newContent .= "\n";
            }

            $ssh->execute('echo '.escapeshellarg($newContent)." > {$whitelistPath}");

            // Mark entries as removed in database
            foreach ($entries as $entry) {
                $entry->markAsRemoved();
            }

            Log::info('Successfully removed expired entries from host', [
                'host' => $host->fqdn,
                'ips' => $ipsToRemove,
            ]);
        } finally {
            // Clean up temporary SSH key
            $this->cleanupTempSshKey($host->hash);
        }
    }

    /**
     * Generate temporary SSH key file
     */
    private function generateTempSshKey(string $hash): string
    {
        $keyPath = storage_path('app/.ssh/bfm_cleanup_'.uniqid());

        if (! file_exists(dirname($keyPath))) {
            mkdir(dirname($keyPath), 0700, true);
        }

        file_put_contents($keyPath, $hash);
        chmod($keyPath, 0600);

        return $keyPath;
    }

    /**
     * Clean up temporary SSH key file
     */
    private function cleanupTempSshKey(string $hash): void
    {
        $keyPattern = storage_path('app/.ssh/bfm_cleanup_*');
        foreach (glob($keyPattern) as $keyFile) {
            if (file_exists($keyFile)) {
                unlink($keyFile);
            }
        }
    }
}
