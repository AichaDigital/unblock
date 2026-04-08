<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Host;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

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

        $knownHostsPath = getenv('HOME').'/.ssh/known_hosts';
        $succeeded = 0;
        $failed = 0;

        foreach ($hosts as $host) {
            $this->info("  [{$host->fqdn}:{$host->port_ssh}] scanning...");

            $this->removeExistingHostKey($knownHostsPath, $host->ip, $host->fqdn);

            $command = sprintf('ssh-keyscan -p %d %s', $host->port_ssh, $host->ip);
            $process = Process::fromShellCommandline($command);
            $process->setTimeout(15);
            $process->run();

            if (! $process->isSuccessful() || empty(trim($process->getOutput()))) {
                $this->error("    FAILED: {$host->fqdn} — {$process->getErrorOutput()}");
                $failed++;

                continue;
            }

            file_put_contents($knownHostsPath, $process->getOutput(), FILE_APPEND);

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
        if (! file_exists($knownHostsPath)) {
            return;
        }

        $lines = file($knownHostsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $filteredLines = array_filter($lines, function ($line) use ($ip, $fqdn) {
            return ! str_contains($line, $ip) && ! str_contains($line, $fqdn);
        });

        file_put_contents($knownHostsPath, implode(PHP_EOL, $filteredLines).PHP_EOL);
    }
}
