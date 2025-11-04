<?php

declare(strict_types=1);

namespace App\Console\Commands\Develop;

use App\Models\Host;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Verify Wrapper Command
 *
 * Verifies that the SSH wrapper script on remote servers contains
 * all required commands used by Unblock core functionality.
 */
class VerifyWrapperCommand extends Command
{
    protected $signature = 'develop:verify-wrapper
                            {--host= : Host ID to verify (optional, will prompt if not provided)}
                            {--all : Verify all hosts}';

    protected $description = 'Verify SSH wrapper script has all required commands';

    /**
     * Core commands used by Unblock
     */
    private const REQUIRED_COMMANDS = [
        // Shared - CSF Commands
        'csf -g 1.2.3.4' => 'Firewall IP check',
        'csf -dr 1.2.3.4' => 'Remove from deny list',
        'csf -tr 1.2.3.4' => 'Remove from temp block',
        'csf -ta 1.2.3.4 3600' => 'Temporary allow (whitelist)',
        'csf -t' => 'Show temp blocks',
        'csf -v' => 'CSF version',

        // Shared - DirectAdmin BFM
        'cat /usr/local/directadmin/data/admin/ip_blacklist' => 'DA BFM blacklist check',
        'sed -i \'/^1\\.2\\.3\\.4(\\s|$)/d\' /usr/local/directadmin/data/admin/ip_blacklist' => 'DA BFM remove IP',
        'echo \'1.2.3.4\' >> /usr/local/directadmin/data/admin/ip_whitelist' => 'DA BFM whitelist add',

        // Shared - Diagnostics
        'whoami' => 'Connection test',
        'cat /etc/csf/csf.deny' => 'CSF deny list',
        'cat /var/lib/csf/csf.tempip' => 'CSF temp blocks',

        // Natural Mode - cPanel API
        'whmapi1 listaccts --output=json' => 'cPanel account list (sync:accounts)',
        'uapi --user=testuser --output=json DomainInfo list_domains' => 'cPanel domain list per user (sync:accounts)',

        // Natural Mode - DirectAdmin Files
        'ls -1 /usr/local/directadmin/data/users' => 'DA user list (sync:accounts)',
        'cat /usr/local/directadmin/data/users/testuser/user.conf' => 'DA user config (sync:accounts)',
        'cat /usr/local/directadmin/data/users/testuser/domains.list' => 'DA domain list (sync:accounts)',

        // Simple Mode - Log Search
        'grep 1.2.3.4 /var/log/exim_mainlog' => 'Exim log search (simple mode)',
        'grep example.com /var/log/maillog' => 'Mail log search (simple mode)',
        'cat /var/log/nginx/modsec_audit.log' => 'ModSecurity log (simple mode)',
    ];

    public function handle(): int
    {
        $this->info('🔍 Verifying SSH Wrapper Configuration');
        $this->newLine();

        // Get hosts to verify
        $hosts = $this->getHostsToVerify();

        if ($hosts->isEmpty()) {
            $this->warn('No hosts found to verify');

            return self::FAILURE;
        }

        $allPassed = true;

        foreach ($hosts as $host) {
            $result = $this->verifyHost($host);
            if (! $result) {
                $allPassed = false;
            }
        }

        $this->newLine();
        if ($allPassed) {
            $this->info('✅ All wrapper verifications passed');

            return self::SUCCESS;
        } else {
            $this->error('❌ Some wrappers have missing commands');
            $this->warn('💡 Update wrapper script: https://github.com/AichaDigital/unblock/blob/main/docs/ssh-keys-setup.md');

            return self::FAILURE;
        }
    }

    private function getHostsToVerify()
    {
        if ($this->option('all')) {
            return Host::whereNotNull('hash')->get();
        }

        $hostId = $this->option('host');

        if (! $hostId) {
            $hosts = Host::whereNotNull('hash')->get();

            if ($hosts->isEmpty()) {
                return collect();
            }

            $choices = $hosts->mapWithKeys(fn ($h) => [$h->id => "{$h->fqdn} ({$h->panel->value})"]);

            $hostId = $this->choice(
                'Select host to verify',
                $choices->toArray(),
                $hosts->first()->id
            );
        }

        $host = Host::find($hostId);

        return $host ? collect([$host]) : collect();
    }

    private function verifyHost(Host $host): bool
    {
        $this->info("📡 Verifying: {$host->fqdn} ({$host->panel->value})");
        $this->newLine();

        $missing = [];
        $requiredForHost = $this->getRequiredCommandsForHost($host);

        $this->line("Testing {$requiredForHost->count()} commands...");
        $this->newLine();

        foreach ($requiredForHost as $command => $description) {
            $passed = $this->testCommand($host, $command, $description);

            if (! $passed) {
                $missing[] = $command;
            }
        }

        $this->newLine();

        if (empty($missing)) {
            $this->info("✅ {$host->fqdn}: All commands working");

            return true;
        } else {
            $this->error("❌ {$host->fqdn}: {$missing->count()} commands blocked");
            $this->warn('Missing commands:');
            foreach ($missing as $cmd) {
                $this->line("  - {$cmd}");
            }

            return false;
        }
    }

    private function testCommand(Host $host, string $command, string $description): bool
    {
        try {
            $process = \Illuminate\Support\Facades\Process::timeout(10)
                ->run("ssh -i {$this->getKeyPath($host)} -p {$host->port_ssh} -o StrictHostKeyChecking=no root@{$host->fqdn} \"{$command}\" 2>&1");

            $output = $process->output();

            // Check for wrapper denial
            $isDenied = str_contains($output, 'ERROR: Command not allowed') ||
                        str_contains($output, 'ERROR: Log file not allowed') ||
                        str_contains($output, 'ERROR: File access not allowed');

            if ($isDenied) {
                $this->line("  ❌ {$description}");

                return false;
            }

            $this->line("  ✅ {$description}");

            return true;

        } catch (\Exception $e) {
            $this->line("  ⚠️  {$description} (error: {$e->getMessage()})");

            return false;
        }
    }

    private function getRequiredCommandsForHost(Host $host)
    {
        $commands = collect(self::REQUIRED_COMMANDS);

        // Filter by panel type
        if ($host->panel->value === 'cpanel') {
            // Remove DA-specific commands
            $commands = $commands->reject(fn ($desc, $cmd) => str_contains($cmd, 'directadmin')
            );
        } elseif (in_array($host->panel->value, ['directadmin', 'da'])) {
            // Remove cPanel-specific commands
            $commands = $commands->reject(fn ($desc, $cmd) => str_contains($cmd, 'whmapi1') || str_contains($cmd, 'uapi')
            );
        }

        return $commands;
    }

    private function getKeyPath(Host $host): string
    {
        // Create temporary key file
        $keyContent = $host->hash;
        $tempFile = tempnam(sys_get_temp_dir(), 'ssh_key_');
        file_put_contents($tempFile, $keyContent);
        chmod($tempFile, 0600);

        register_shutdown_function(function () use ($tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        });

        return $tempFile;
    }
}
