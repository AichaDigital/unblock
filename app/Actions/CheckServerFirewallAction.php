<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\{
    FirewallException
};
use App\Models\Host;
use App\Services\Firewall\{DirectAdminFirewallAnalyzer};
use App\Services\FirewallService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Action to check server firewall status
 *
 * This action handles:
 * - SSH key generation and setup
 * - CSF checks
 * - Panel-specific service checks
 *
 * ENHANCED: Now uses DirectAdminFirewallAnalyzer for robust analysis when possible
 */
class CheckServerFirewallAction
{
    use AsAction;

    public function __construct(
        protected FirewallService $firewallService
    ) {}

    /**
     * Handle server firewall check process
     *
     * @param  string  $ip  IP address to check
     * @param  int  $hostId  Host ID to check
     * @return array{
     *     is_blocked: bool,
     *     logs: array<string, string>,
     *     key_name: string,
     *     error?: string
     * }
     *
     * @throws FirewallException When panel is not supported
     */
    public function handle(string $ip, int $hostId): array
    {
        $keyName = '';
        try {
            try {
                $host = Host::findOrFail($hostId);
            } catch (ModelNotFoundException $e) {
                return [
                    'is_blocked' => false,
                    'logs' => [],
                    'key_name' => $keyName,
                    'error' => $e->getMessage(),
                ];
            }

            $keyName = $this->firewallService->generateSshKey($host->hash);
            $this->firewallService->prepareMultiplexingPath();

            // ENHANCED: Use DirectAdminFirewallAnalyzer for robust analysis when available
            if ($this->isDirectAdminPanel($host)) {
                return $this->handleDirectAdminWithAnalyzer($ip, $host, $keyName);
            }

            // Fallback to original logic for other panels
            return $this->handleWithOriginalLogic($ip, $host, $keyName);

        } catch (FirewallException $e) {
            return [
                'is_blocked' => false,
                'logs' => [],
                'key_name' => $keyName,
                'error' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            return [
                'is_blocked' => false,
                'logs' => [],
                'key_name' => $keyName,
                'error' => $e->getMessage(),
            ];
        } finally {
            if ($keyName) {
                $this->firewallService->removeMultiplexingPath($keyName);
            }
        }
    }

    /**
     * Check if the host uses DirectAdmin panel
     */
    private function isDirectAdminPanel(Host $host): bool
    {
        return $host->panel === \App\Enums\PanelType::DIRECTADMIN;
    }

    /**
     * Handle DirectAdmin hosts using the robust analyzer
     */
    private function handleDirectAdminWithAnalyzer(string $ip, Host $host, string $keyName): array
    {
        Log::info('Using DirectAdminFirewallAnalyzer for enhanced analysis', [
            'ip' => $ip,
            'host' => $host->fqdn,
            'panel' => $host->panel,
        ]);

        try {
            // Create analyzer instance
            $analyzer = new DirectAdminFirewallAnalyzer($this->firewallService, $host);

            // Create a simple session object compatible with the analyzer
            $session = new class($keyName)
            {
                public function __construct(private string $sshKeyPath) {}

                public function getSshKeyPath(): string
                {
                    return $this->sshKeyPath;
                }

                public function cleanup(): void
                {
                    // No-op, cleanup is handled by CheckServerFirewallAction
                }

                public function __toString(): string
                {
                    return $this->sshKeyPath;
                }
            };

            // Perform analysis
            $analysisResult = $analyzer->analyze($ip, $session);

            // Convert analysis result to expected format with compatible keys
            $originalLogs = $analysisResult->getLogs();
            $logs = $this->mapLogsToOriginalFormat($originalLogs);
            $isBlocked = $analysisResult->isBlocked();

            Log::info('DirectAdminFirewallAnalyzer analysis completed', [
                'ip' => $ip,
                'host' => $host->fqdn,
                'is_blocked' => $isBlocked,
                'logs_count' => count($logs),
            ]);

            return [
                'is_blocked' => $isBlocked,
                'logs' => $logs,
                'key_name' => $keyName,
            ];

        } catch (Exception $e) {
            Log::warning('DirectAdminFirewallAnalyzer failed, falling back to original logic', [
                'ip' => $ip,
                'host' => $host->fqdn,
                'error' => $e->getMessage(),
            ]);

            // Fallback to original logic if analyzer fails
            return $this->handleWithOriginalLogic($ip, $host, $keyName);
        }
    }

    /**
     * Handle with original logic for non-DirectAdmin or fallback cases
     */
    private function handleWithOriginalLogic(string $ip, Host $host, string $keyName): array
    {
        // Check standard CSF
        $csfOutput = $this->firewallService->checkProblems($host, $keyName, 'csf', $ip);
        $this->firewallService->setData('csf', $csfOutput);

        // Check special CSF rules
        $csfSpecialsOutput = $this->firewallService->checkProblems($host, $keyName, 'csf_specials', $ip);
        $this->firewallService->setData('csf_specials', $csfSpecialsOutput);

        // Check panel-specific services if panel is supported
        if ($host->panel !== null) {
            match ($host->panel) {
                \App\Enums\PanelType::DIRECTADMIN => $this->checkDirectAdminServices($host, $keyName, $ip),
                \App\Enums\PanelType::CPANEL => $this->checkCpanelServices($host, $keyName, $ip),
                \App\Enums\PanelType::NONE => null, // No panel-specific checks
            };
        }

        $logs = $this->firewallService->getData();

        return [
            'is_blocked' => true, // Original logic always returned true
            'logs' => $logs,
            'key_name' => $keyName,
        ];
    }

    /**
     * Check DirectAdmin specific services (original logic)
     */
    protected function checkDirectAdminServices(Host $host, string $keyName, string $ip): void
    {
        $services = [
            'exim_directadmin',
            'dovecot_directadmin',
            'mod_security_da',
            'da_bfm_check', // Check DirectAdmin BFM blacklist
        ];

        foreach ($services as $service) {
            $output = $this->firewallService->checkProblems($host, $keyName, $service, $ip);
            $this->firewallService->setData($service, $output);
        }
    }

    /**
     * Check cPanel specific services (original logic)
     */
    protected function checkCpanelServices(Host $host, string $keyName, string $ip): void
    {
        $services = [
            'exim_cpanel',
            'dovecot_cpanel',
        ];

        foreach ($services as $service) {
            $output = $this->firewallService->checkProblems($host, $keyName, $service, $ip);
            $this->firewallService->setData($service, $output);
        }
    }

    /**
     * Map analyzer logs to original format expected by the system
     */
    private function mapLogsToOriginalFormat(array $analyzerLogs): array
    {
        $mappedLogs = [];

        // Map analyzer keys to original system keys
        $keyMapping = [
            'csf' => 'csf',
            'csf_deny' => 'csf_specials', // Map csf_deny to csf_specials for compatibility
            'csf_tempip' => 'csf_tempip',
            'csf_specials' => 'csf_specials',
            'exim' => 'exim_directadmin',
            'dovecot' => 'dovecot_directadmin',
            'mod_security' => 'mod_security_da',
            'da_bfm' => 'da_bfm_check',
        ];

        foreach ($analyzerLogs as $analyzerKey => $logData) {
            $originalKey = $keyMapping[$analyzerKey] ?? $analyzerKey;
            $mappedLogs[$originalKey] = $logData;
        }

        return $mappedLogs;
    }
}
