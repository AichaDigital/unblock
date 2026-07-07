<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Host;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{File, Process};

class VerifyAllHostKeysCommand extends Command
{
    protected $signature = 'verify:host-keys {--force : Re-verify all hosts, even already verified}';

    protected $description = 'Verify SSH host keys for all active hosts and update known_hosts';

    public function handle(): int
    {
        $query = Host::whereNull('deleted_at');

        if (! $this->option('force')) {
            $query->where('ssh_host_key_verified', false);
        }

        $hosts = $query->get();

        if ($hosts->isEmpty()) {
            $this->info('No hosts to verify.');

            return self::SUCCESS;
        }

        $this->info("Verifying {$hosts->count()} host(s)...");

        $knownHostsPath = (string) config('unblock.ssh.known_hosts_path');
        $succeeded = 0;
        $failed = 0;

        foreach ($hosts as $host) {
            $this->info("  [{$host->fqdn}:{$host->port_ssh}] scanning...");

            $this->removeExistingHostKey($knownHostsPath, $host->ip, $host->fqdn);

            $command = sprintf('ssh-keyscan -p %d %s', $host->port_ssh, $host->ip);
            $result = Process::timeout(15)->run($command);

            if (! $result->successful() || empty(trim($result->output()))) {
                $this->error("    FAILED: {$host->fqdn} — {$result->errorOutput()}");
                $failed++;

                continue;
            }

            File::append($knownHostsPath, $result->output());

            $host->update([
                'ssh_host_key_verified' => true,
                'ssh_host_key_verified_at' => now(),
            ]);

            $this->info('    OK: host key verified');
            $succeeded++;
        }

        $this->newLine();
        $this->info("Done: {$succeeded} verified, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function removeExistingHostKey(string $knownHostsPath, string $ip, string $fqdn): void
    {
        if (! File::exists($knownHostsPath)) {
            return;
        }

        $filteredLines = File::lines($knownHostsPath)
            ->filter(function (string $line) use ($ip, $fqdn) {
                return $line !== '' && ! str_contains($line, $ip) && ! str_contains($line, $fqdn);
            });

        File::put($knownHostsPath, $filteredLines->implode(PHP_EOL).PHP_EOL);
    }
}
