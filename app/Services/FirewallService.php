<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\{
    ConnectionFailedException,
    InvalidIpException,
    InvalidKeyException
};
use App\Models\Host;
use Illuminate\Support\Facades\{File, Log, Storage};
use Spatie\Ssh\Ssh;

/**
 * Simplified Firewall Service
 * Direct SSH execution with Spatie SSH - no overengineering
 */
class FirewallService
{
    private array $data = [];

    private string $modSecurityOutput = '';

    /**
     * Execute firewall command via SSH
     *
     * @param  Host  $host  Server to connect to
     * @param  string  $keyPath  SSH key file path
     * @param  string  $command  Command type to execute
     * @param  string  $ip  IP address to check/process
     * @return string Command output
     */
    public function checkProblems(Host $host, string $keyPath, string $command, string $ip): string
    {
        // Validate IP
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidIpException($ip, "Invalid IP: {$ip}");
        }

        // Build command
        $cmd = $this->buildCommand($command, $ip);
        if (! $cmd) {
            throw new InvalidKeyException("Unknown command: {$command}");
        }

        try {
            // SIMPLE SPATIE SSH - NO COMPLICATIONS
            $ssh = Ssh::create('root', $host->fqdn, $host->port_ssh ?? 22)
                ->usePrivateKey($keyPath)
                ->disableStrictHostKeyChecking()
                ->setTimeout(30);

            $process = $ssh->execute($cmd);

            if (! $process->isSuccessful()) {
                Log::warning('SSH command failed', [
                    'host' => $host->fqdn,
                    'command' => $command,
                    'ip' => $ip,
                    'error' => $process->getErrorOutput(),
                ]);

                return '';
            }

            $output = trim($process->getOutput());

            // Process ModSecurity JSON if needed
            if ($command === 'mod_security_da' && ! empty($output)) {
                return $this->processModSecurityJson($output, $ip);
            }

            return $output;

        } catch (\Exception $e) {
            $msg = "SSH connection failed to {$host->fqdn}: {$e->getMessage()}";
            Log::error($msg, [
                'host_id' => $host->id,
                'host_fqdn' => $host->fqdn,
                'command' => $command,
                'ip' => $ip,
            ]);
            throw new ConnectionFailedException($msg, previous: $e);
        }
    }

    /**
     * Prepare SSH multiplexing directory (noop-safe for compatibility)
     */
    public function prepareMultiplexingPath(): void
    {
        $directoryPath = '/tmp/cm';
        if (! File::exists($directoryPath)) {
            // Best-effort directory creation; ignore failures to remain backward-compatible
            @File::makeDirectory($directoryPath, 0755, true);
        }
    }

    /**
     * Backward-compatible cleanup method invoked by callers using key path variable.
     * Removes the SSH key file if it exists.
     */
    public function removeMultiplexingPath(string $keyPath): void
    {
        if ($keyPath !== '' && file_exists($keyPath)) {
            Storage::disk('ssh')->delete(basename($keyPath));
        }
    }

    /**
     * Build SSH command based on type and IP
     */
    private function buildCommand(string $type, string $ip): ?string
    {
        $ip_escaped = escapeshellarg($ip);

        return match ($type) {
            'csf' => "csf -g {$ip}",
            'csf_deny_check' => "cat /etc/csf/csf.deny | grep {$ip_escaped} || true",
            'csf_tempip_check' => "cat /var/lib/csf/csf.tempip | grep {$ip_escaped} || true",
            'mod_security_da' => "cat /var/log/nginx/modsec_audit.log | grep {$ip_escaped} || true",
            'exim_directadmin' => "cat /var/log/exim/mainlog | grep -Ea {$ip_escaped} | grep 'authenticator failed'",
            'dovecot_directadmin' => "cat /var/log/mail.log | grep -Ea {$ip_escaped} | grep 'auth failed'",
            // FIXED: Use exact IP matching to avoid false positives (e.g., 10.192.168.1.100 matching 192.168.1.100)
            'da_bfm_check' => "cat /usr/local/directadmin/data/admin/ip_blacklist | grep -E '^{$ip_escaped}(\\s|\$)' || true",
            'da_bfm_remove' => "sed -i '/^{$ip_escaped}(\\s|\$)/d' /usr/local/directadmin/data/admin/ip_blacklist",
            'da_bfm_whitelist_add' => "echo '{$ip}' >> /usr/local/directadmin/data/admin/ip_whitelist",
            'unblock' => "csf -dr {$ip} && csf -tr {$ip} && csf -ta {$ip} 86400",
            'whitelist' => "csf -ta {$ip} 86400",
            'whitelist_7200' => "csf -ta {$ip} ".max(60, (int) (config('unblock.hq.ttl') ?? 7200)),
            default => null
        };
    }

    /**
     * Process ModSecurity JSON output and filter by IP
     */
    private function processModSecurityJson(string $json_output, string $targetIp): string
    {
        $lines = collect(explode("\n", $json_output))
            ->filter(fn ($line) => ! empty(trim($line)))
            ->map(function ($line) use ($targetIp) {
                try {
                    $data = json_decode(trim($line), true, flags: JSON_THROW_ON_ERROR);

                    $clientIp = data_get($data, 'transaction.client_ip');
                    if ($targetIp && $clientIp !== $targetIp) {
                        return null;
                    }

                    // Handle both message structures: root level and transaction level
                    $messagesData = data_get($data, 'messages', []) ?: data_get($data, 'transaction.messages', []);

                    $messages = collect($messagesData)
                        ->map(fn ($msg) => sprintf('[%s] %s',
                            data_get($msg, 'details.ruleId'),
                            data_get($msg, 'message')
                        ))
                        ->filter()
                        ->values();

                    return $messages->isNotEmpty() ? sprintf(
                        '[%s] IP: %s | URI: %s | Rules: %s',
                        data_get($data, 'transaction.time_stamp'),
                        $clientIp,
                        data_get($data, 'transaction.request.uri'),
                        $messages->implode(', ')
                    ) : null;

                } catch (\JsonException) {
                    return null;
                }
            })
            ->filter()
            ->values();

        return $lines->implode("\n");
    }

    /**
     * Generate SSH key file and return path
     * FIXED: Normalize SSH keys exactly like TestHostConnectionCommand
     */
    public function generateSshKey(string $hash): string
    {
        // Normalize line endings
        $normalizedKey = str_replace(["\r\n", "\r"], "\n", $hash);
        if (! str_ends_with($normalizedKey, "\n")) {
            $normalizedKey .= "\n";
        }

        $fileName = 'key_'.uniqid();

        // Use Laravel's Storage facade to allow for easier testing and abstraction.
        // The 'ssh' disk is configured in config/filesystems.php.
        // The visibility 'private' ensures correct file permissions (0600).
        $isSuccess = Storage::disk('ssh')->put($fileName, $normalizedKey, 'private');

        if (! $isSuccess) {
            throw new InvalidKeyException('Failed to write SSH key file using Storage facade.');
        }

        // Return the full path for the SSH command.
        return Storage::disk('ssh')->path($fileName);
    }

    /**
     * Escape IP for grep command
     */
    private function escapeIpForGrep(string $ip): string
    {
        return str_replace('.', '\.', $ip);
    }

    /**
     * Add error if string starts with error
     */
    private function addErrorIfStartsWithError(string $output, string $key): void
    {
        if (str_starts_with(strtolower($output), 'error')) {
            $this->data['errors'][$key] = $output;
        }
    }

    /**
     * Set data array
     */
    public function setData(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Get data array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get errors array
     */
    public function getErrors(): array
    {
        return $this->data['errors'] ?? [];
    }

    /**
     * Set ModSecurity output for testing
     */
    public function setModSecurityOutput(string $output): void
    {
        $this->modSecurityOutput = $output;
    }

    /**
     * Extract data from ModSecurity (legacy method for backward compatibility)
     */
    private function extractDataFromModSecurity(string $output, string $ip): string
    {
        return $this->processModSecurityJson($output, $ip);
    }
}
