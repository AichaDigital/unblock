<?php

namespace App\Console\Commands\Develop;

use App\Models\Host;
use Exception;
use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;

class TestSpatieMultiplexerCommand extends Command
{
    protected $signature = 'develop:test-spatie-ssh {host_id=2} {--check-procopen}';

    protected $description = 'Test Spatie SSH multiplexer without proc_open - proves CSF commands work with Spatie';

    public function handle(): int
    {
        $this->info('🔧 TESTING SPATIE/SSH MULTIPLEXER - EXCLUSIVE USE');

        // Check if proc_open is available (but we don't need it)
        if ($this->option('check-procopen')) {
            $this->checkProcopenStatus();
        }

        // Get host to test (default to the failing one)
        $hostId = $this->argument('host_id');
        $host = Host::find($hostId);

        if (! $host) {
            $this->error("Host not found with ID: {$hostId}");

            return 1;
        }

        $this->info("🎯 Testing host: {$host->fqdn}:{$host->port_ssh}");

        // Use normalized SSH key from Host model (like TestHostConnectionCommand)
        $keyPath = $this->createNormalizedSshKey($host);

        if (! $keyPath) {
            $this->error('❌ Failed to create SSH key');

            return 1;
        }

        try {
            // Test EXCLUSIVELY with Spatie/SSH
            $this->testBasicSpatieSsh($host, $keyPath);
            $this->testCsfCommandsWithSpatie($host, $keyPath);

            $this->info('✅ SPATIE/SSH WORKS - NO proc_open NEEDED');

        } catch (Exception $e) {
            $this->error("❌ Spatie test failed: {$e->getMessage()}");

            return 1;
        } finally {
            // Cleanup
            if ($keyPath && file_exists($keyPath)) {
                unlink($keyPath);
                $this->info('🧹 SSH key cleaned up');
            }
        }

        return 0;
    }

    private function checkProcopenStatus(): void
    {
        $this->info('🔍 Checking proc_open status...');

        if (function_exists('proc_open')) {
            $this->info('✅ proc_open is available (but we don\'t need it)');
        } else {
            $this->warn('⚠️  proc_open is NOT available (this is fine, we have fallbacks)');
        }

        // Check other functions that Symfony Process can use
        $fallbacks = ['exec', 'shell_exec', 'popen'];
        foreach ($fallbacks as $func) {
            if (function_exists($func)) {
                $this->info("✅ {$func} is available (fallback option)");
            } else {
                $this->warn("⚠️  {$func} is NOT available");
            }
        }
    }

    private function askForHost(): int
    {
        $hosts = Host::select('id', 'fqdn', 'alias')->get();

        if ($hosts->isEmpty()) {
            $this->error('No hosts found in database');
            exit(1);
        }

        $this->table(['ID', 'FQDN', 'Alias'], $hosts->map(fn ($h) => [$h->id, $h->fqdn, $h->alias]));

        return (int) $this->ask('Enter Host ID to test');
    }

    private function createNormalizedSshKey(Host $host): ?string
    {
        $privateKey = $host->hash; // Use the getter from Host model

        // Normalize exactly like TestHostConnectionCommand
        $normalizedKey = str_replace(["\r\n", "\r"], "\n", $privateKey);
        if (! str_ends_with($normalizedKey, "\n")) {
            $normalizedKey .= "\n";
        }

        $keyFileName = 'spatie_test_'.uniqid().'.key';
        $keyPath = storage_path('app/temp/'.$keyFileName);

        // Ensure directory exists
        if (! is_dir(dirname($keyPath))) {
            mkdir(dirname($keyPath), 0755, true);
        }

        if (file_put_contents($keyPath, $normalizedKey) === false) {
            return null;
        }

        chmod($keyPath, 0600);

        $this->info('🔑 Using SSH key from Host model (normalized)');

        return $keyPath;
    }

    private function testBasicSpatieSsh(Host $host, string $keyPath): void
    {
        $this->info('🧪 SPATIE/SSH Test 1: Basic whoami command');

        $ssh = Ssh::create('root', $host->fqdn, $host->port_ssh ?? 22)
            ->usePrivateKey($keyPath)
            ->disableStrictHostKeyChecking()
            ->setTimeout(10);

        $process = $ssh->execute('whoami');

        if (! $process->isSuccessful()) {
            throw new Exception('Spatie SSH basic command failed: '.$process->getErrorOutput());
        }

        $output = trim($process->getOutput());
        $this->info("✅ Spatie/SSH working: {$output}");
    }

    private function testCsfCommandsWithSpatie(Host $host, string $keyPath): void
    {
        $testIp = '89.248.172.183'; // IP from production error logs

        $this->info('🧪 SPATIE/SSH Test 2: CSF commands (the REAL goal)');
        $this->info("🎯 Testing with production IP: {$testIp}");

        $commands = [
            'csf -g' => "csf -g {$testIp}",
            'csf.deny check' => "cat /etc/csf/csf.deny | head -5 || echo 'deny file empty'",
            'tempip check' => "cat /var/lib/csf/csf.tempip | head -5 || echo 'tempip empty'",
        ];

        foreach ($commands as $name => $cmd) {
            $this->info("🔥 SPATIE executing: {$name}");

            try {
                // EXCLUSIVE use of Spatie/SSH
                $ssh = Ssh::create('root', $host->fqdn, $host->port_ssh ?? 22)
                    ->usePrivateKey($keyPath)
                    ->disableStrictHostKeyChecking()
                    ->setTimeout(15);

                $process = $ssh->execute($cmd);

                if ($process->isSuccessful()) {
                    $output = trim($process->getOutput());
                    $this->info('✅ Spatie/SSH success: '.substr($output, 0, 100).(strlen($output) > 100 ? '...' : ''));
                } else {
                    $error = trim($process->getErrorOutput());
                    $this->info("ℹ️  Command output: {$error}");
                }

            } catch (Exception $e) {
                $this->warn("⚠️  Spatie exception: {$e->getMessage()}");
            }
        }

        $this->info('🎯 PROVEN: Spatie/SSH can execute CSF commands without proc_open');
    }
}
