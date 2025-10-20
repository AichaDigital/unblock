<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\{CommandExecutionException, CsfServiceException};
use App\Models\Host;
use App\Services\Firewall\FirewallAnalysisResult;
use Illuminate\Support\Facades\Log;

/**
 * Firewall Unblocker - Single Responsibility Pattern
 *
 * Handles all IP unblocking operations following OPML business rules:
 * - If IP blocked in CSF: CSF unblock + temporal whitelist
 * - If IP only in BFM: BFM removal only (NO CSF temporal whitelist)
 * - Command execution and error handling
 * - Validation of unblock operations
 */
class FirewallUnblocker
{
    public function __construct(
        private SshConnectionManager $sshManager
    ) {}

    /**
     * Unblock an IP address based on analysis results and OPML business rules
     *
     * @param  string  $ipAddress  IP to unblock
     * @param  Host  $host  Target host
     * @param  FirewallAnalysisResult  $analysisResult  Analysis result that determines which operations to perform
     * @return array Results of unblock operations
     */
    public function unblockIp(string $ipAddress, Host $host, FirewallAnalysisResult $analysisResult): array
    {
        $session = $this->sshManager->createSession($host);
        $results = [];

        try {
            $logs = $analysisResult->getLogs();

            // Determine what types of blocks were found
            $hasCsfBlocks = $this->hasCsfBlocks($logs);
            $hasBfmBlocks = $this->hasBfmBlocks($logs);

            Log::info('Firewall Unblocker: Determining unblock strategy', [
                'ip' => $ipAddress,
                'host' => $host->fqdn,
                'has_csf_blocks' => $hasCsfBlocks,
                'has_bfm_blocks' => $hasBfmBlocks,
                'available_logs' => array_keys($logs),
            ]);

            // 1. If IP is blocked in CSF systems -> CSF unblock + temporal whitelist
            if ($hasCsfBlocks) {
                Log::info('Firewall Unblocker: CSF blocks detected - performing CSF operations', [
                    'ip' => $ipAddress,
                    'host' => $host->fqdn,
                ]);
                $results['csf'] = $this->performCsfOperations($session, $ipAddress);
            } else {
                Log::info('Firewall Unblocker: No CSF blocks detected - skipping CSF operations', [
                    'ip' => $ipAddress,
                    'host' => $host->fqdn,
                ]);
            }

            // 2. If IP is in BFM blacklist (DirectAdmin only) -> remove from BFM
            if ($hasBfmBlocks && $host->panel === 'directadmin') {
                Log::info('Firewall Unblocker: BFM blocks detected - performing BFM removal', [
                    'ip' => $ipAddress,
                    'host' => $host->fqdn,
                ]);
                $results['bfm'] = $this->performBfmOperations($session, $ipAddress);
            } elseif ($host->panel === 'directadmin') {
                Log::info('Firewall Unblocker: No BFM blocks detected - skipping BFM operations', [
                    'ip' => $ipAddress,
                    'host' => $host->fqdn,
                ]);
            }

            // 3. Log the OPML compliance summary
            Log::info('Firewall Unblocker: OPML compliance summary', [
                'ip' => $ipAddress,
                'host' => $host->fqdn,
                'csf_operations_performed' => isset($results['csf']),
                'bfm_operations_performed' => isset($results['bfm']),
                'opml_rule_applied' => $this->getOpmlRuleApplied($hasCsfBlocks, $hasBfmBlocks),
            ]);

            return $results;

        } finally {
            $session->cleanup();
        }
    }

    /**
     * Determine if CSF blocks were found in analysis
     */
    private function hasCsfBlocks(array $logs): bool
    {
        // Check for CSF primary analysis - must contain blocking patterns
        if (! empty($logs['csf'] ?? '')) {
            $csfContent = $logs['csf'];
            if (str_contains($csfContent, 'DENYIN') ||
                str_contains($csfContent, 'DROP') ||
                str_contains($csfContent, 'Temporary Blocks')) {
                return true;
            }
        }

        // Check for CSF deny file blocks - any content means blocked
        if (! empty($logs['csf_deny'] ?? '')) {
            return true;
        }

        // Check for CSF temporary blocks - any content means blocked
        if (! empty($logs['csf_tempip'] ?? '')) {
            return true;
        }

        return false;
    }

    /**
     * Determine if BFM blocks were found in analysis
     */
    private function hasBfmBlocks(array $logs): bool
    {
        return ! empty($logs['da_bfm'] ?? '');
    }

    /**
     * Get description of OPML rule applied
     */
    private function getOpmlRuleApplied(bool $hasCsfBlocks, bool $hasBfmBlocks): string
    {
        if ($hasCsfBlocks && $hasBfmBlocks) {
            return 'CSF blocks + BFM blocks: CSF unblock/whitelist + BFM removal';
        }

        if ($hasCsfBlocks && ! $hasBfmBlocks) {
            return 'CSF blocks only: CSF unblock/whitelist';
        }

        if (! $hasCsfBlocks && $hasBfmBlocks) {
            return 'BFM blocks only: BFM removal (NO CSF temporal whitelist)';
        }

        return 'No blocks detected: no operations performed';
    }

    /**
     * Perform CSF unblock and whitelist operations
     */
    private function performCsfOperations(SshSession $session, string $ipAddress): array
    {
        $results = [];

        try {
            // Unblock the IP
            $unblockCommand = "csf -dr {$ipAddress}";
            $unblockOutput = $session->execute($unblockCommand);
            $results['unblock'] = [
                'command' => $unblockCommand,
                'output' => $unblockOutput,
                'success' => true,
            ];

            // Add to temporal whitelist (24 hours)
            $whitelistCommand = "csf -ta {$ipAddress} 86400";
            $whitelistOutput = $session->execute($whitelistCommand);
            $results['whitelist'] = [
                'command' => $whitelistCommand,
                'output' => $whitelistOutput,
                'success' => true,
            ];

            Log::info('CSF operations completed successfully', [
                'ip' => $ipAddress,
                'host' => $session->getHost()->fqdn,
                'operations' => ['unblock', 'temporal_whitelist_24h'],
            ]);

        } catch (\Exception $e) {
            Log::error('CSF operations failed', [
                'ip' => $ipAddress,
                'host' => $session->getHost()->fqdn,
                'error' => $e->getMessage(),
            ]);

            throw new CsfServiceException(
                "Failed to perform CSF operations for IP {$ipAddress}: ".$e->getMessage(),
                previous: $e
            );
        }

        return $results;
    }

    /**
     * Perform BFM blacklist removal operations (DirectAdmin only)
     */
    private function performBfmOperations(SshSession $session, string $ipAddress): array
    {
        $results = [];

        try {
            // Use the proper method that follows project rules
            $results = $this->performBfmRemoval($session, $ipAddress);

            Log::info('BFM operations completed successfully', [
                'ip' => $ipAddress,
                'host' => $session->getHost()->fqdn,
                'removed' => $results['verification']['removed'] ?? false,
            ]);

        } catch (\Exception $e) {
            Log::error('BFM operations failed', [
                'ip' => $ipAddress,
                'host' => $session->getHost()->fqdn,
                'error' => $e->getMessage(),
            ]);

            throw new CommandExecutionException(
                "Failed to perform BFM operations for IP {$ipAddress}: ".$e->getMessage(),
                previous: $e
            );
        }

        return $results;
    }

    /**
     * Perform BFM blacklist removal following project rules
     *
     * Following project rules:
     * 1. Don't use sed/awk to manipulate remote files
     * 2. Get the full file content
     * 3. Process it locally in PHP
     * 4. Write back the result
     */
    private function performBfmRemoval(SshSession $session, string $ipAddress): array
    {
        $results = [];

        try {
            // 1. Get the full BFM blacklist file content
            $getContentCommand = 'cat /usr/local/directadmin/data/admin/ip_blacklist';
            $fileContent = $session->execute($getContentCommand);

            $results['get_content'] = [
                'command' => $getContentCommand,
                'success' => true,
            ];

            // 2. Process locally in PHP
            $lines = explode("\n", $fileContent);
            $filteredLines = [];
            $ipFound = false;

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                // Only include lines that DO NOT start with the IP followed by whitespace or end of line
                if (preg_match('/^'.preg_quote($ipAddress, '/').'(\s|$)/', $line)) {
                    $ipFound = true;

                    continue;
                }
                $filteredLines[] = $line;
            }

            // 3. Write back the result
            if ($ipFound) {
                $newContent = implode("\n", $filteredLines);
                if (! empty($newContent)) {
                    $newContent .= "\n"; // Add final newline
                }

                $writeCommand = 'echo '.escapeshellarg($newContent).' > /usr/local/directadmin/data/admin/ip_blacklist';
                $writeOutput = $session->execute($writeCommand);

                $results['removal'] = [
                    'command' => $writeCommand,
                    'output' => '',
                    'success' => true,
                ];
            } else {
                $results['removal'] = [
                    'command' => $getContentCommand,
                    'output' => '',
                    'success' => true,
                    'note' => 'IP not found in blacklist',
                ];
            }

            // 4. Verify removal
            $verifyCommand = "cat /usr/local/directadmin/data/admin/ip_blacklist | grep -w '".$ipAddress."' || echo 'IP not found in blacklist'";
            $verifyOutput = $session->execute($verifyCommand);

            $results['verification'] = [
                'command' => $verifyCommand,
                'output' => $verifyOutput,
                'removed' => str_contains($verifyOutput, 'IP not found in blacklist'),
            ];

            return $results;
        } catch (\Exception $e) {
            Log::error('BFM file processing failed', [
                'ip' => $ipAddress,
                'error' => $e->getMessage(),
            ]);

            throw new CommandExecutionException(
                "Failed to process BFM blacklist for IP {$ipAddress}: ".$e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Build the command to remove IP from BFM blacklist
     *
     * @deprecated Use performBfmRemoval instead to comply with project rules
     */
    private function buildBfmRemovalCommand(string $ipAddress): string
    {
        // This method is deprecated and should not be used
        Log::warning('Using deprecated buildBfmRemovalCommand method - should use performBfmRemoval instead');

        // Command that violates project rules - kept for reference only
        return "sed -i '/^".preg_quote($ipAddress, '/')."\\b/d' /usr/local/directadmin/data/admin/ip_blacklist";
    }

    /**
     * Validate if an IP is properly formatted before unblocking
     */
    public function validateIpAddress(string $ipAddress): bool
    {
        return filter_var($ipAddress, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Get the status of unblock operations
     */
    public function getUnblockStatus(array $results): array
    {
        $status = [
            'overall_success' => true,
            'csf_success' => false,
            'bfm_success' => true, // Default true for non-DirectAdmin
            'operations_performed' => [],
        ];

        // Check CSF status
        if (isset($results['csf'])) {
            $status['csf_success'] =
                ($results['csf']['unblock']['success'] ?? false) &&
                ($results['csf']['whitelist']['success'] ?? false);

            $status['operations_performed'][] = 'csf_unblock';
            $status['operations_performed'][] = 'csf_whitelist';
        }

        // Check BFM status
        if (isset($results['bfm'])) {
            $status['bfm_success'] = $results['bfm']['removal']['success'] ?? false;
            $status['operations_performed'][] = 'bfm_removal';
        }

        $status['overall_success'] = $status['csf_success'] && $status['bfm_success'];

        return $status;
    }
}
