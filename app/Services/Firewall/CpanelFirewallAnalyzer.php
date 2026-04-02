<?php

declare(strict_types=1);

namespace App\Services\Firewall;

use App\Models\Host;
use App\Services\FirewallService;
use Illuminate\Support\Facades\Log;

readonly class CpanelFirewallAnalyzer implements FirewallAnalyzerInterface
{
    private const PANEL_TYPE = 'cpanel';

    /** @var array<string, bool> */
    private array $serviceChecks;

    /**
     * @param  array<string, bool>|null  $serviceChecks
     */
    public function __construct(
        private FirewallService $firewallService,
        private Host $host,
        ?array $serviceChecks = null
    ) {
        $this->serviceChecks = $serviceChecks ?? [
            'csf' => true,
            'exim_cpanel' => true,
            'dovecot_cpanel' => true,
            'lfd_history' => true,
        ];
    }

    public function analyze(string $ipAddress, mixed $session): FirewallAnalysisResult
    {
        // Extraer SSH key path del session (compatible con ambas implementaciones)
        $sshKeyName = (is_object($session) && method_exists($session, 'getSshKeyPath'))
            ? $session->getSshKeyPath()
            : (string) $session; // fallback para compatibilidad

        $results = [];
        $logs = [];
        $wasBlocked = false;

        // Primero verificamos si la IP está bloqueada en CSF
        if (($this->serviceChecks['csf'] ?? false) === true) {
            $csfOutput = $this->firewallService->checkProblems($this->host, $sshKeyName, 'csf', $ipAddress);
            if (! $this->isSshError($csfOutput)) {
                $logs['csf'] = $csfOutput;
                $csfResult = $this->analyzeCsfOutput($csfOutput);
                $results[] = $csfResult;
                if ($csfResult->isBlocked()) {
                    $wasBlocked = true;
                }
            } else {
                $logs['csf'] = '';
                $logs['ssh_errors'][] = $csfOutput;
            }
        }

        // ALWAYS check Exim and Dovecot for context (not just when blocked)
        if (($this->serviceChecks['exim_cpanel'] ?? false) === true) {
            $eximOutput = $this->firewallService->checkProblems($this->host, $sshKeyName, 'exim_cpanel', $ipAddress);
            if (! $this->isSshError($eximOutput)) {
                $logs['exim'] = $eximOutput;
                $results[] = $this->analyzeEximOutput($eximOutput);
            } else {
                $logs['exim'] = '';
                $logs['ssh_errors'][] = $eximOutput;
            }
        }

        if (($this->serviceChecks['dovecot_cpanel'] ?? false) === true) {
            $dovecotOutput = $this->firewallService->checkProblems($this->host, $sshKeyName, 'dovecot_cpanel', $ipAddress);
            if (! $this->isSshError($dovecotOutput)) {
                $logs['dovecot'] = $dovecotOutput;
                $results[] = $this->analyzeDovecotOutput($dovecotOutput);
            } else {
                $logs['dovecot'] = '';
                $logs['ssh_errors'][] = $dovecotOutput;
            }
        }

        // Check LFD history (CONTEXT ONLY - never indicates blocking)
        if ($this->serviceChecks['lfd_history'] ?? false) {
            $lfdOutput = $this->firewallService->checkProblems($this->host, $sshKeyName, 'lfd_history', $ipAddress);
            if (! $this->isSshError($lfdOutput)) {
                $logs['lfd_history'] = $lfdOutput;
            } else {
                $logs['lfd_history'] = '';
                $logs['ssh_errors'][] = $lfdOutput;
            }
            $results[] = new FirewallAnalysisResult(false, ['lfd_history' => $logs['lfd_history']]);
        }

        // Log resultado final para debug
        Log::debug("cPanel firewall analysis completed for {$ipAddress}", [
            'host' => $this->host->fqdn,
            'was_blocked' => $wasBlocked,
            'ssh_errors_count' => count($logs['ssh_errors'] ?? []),
        ]);

        // GARANTIZAR ESTRUCTURA JSON COMPLETA
        $completeLogsStructure = [
            'csf' => $logs['csf'] ?? '',
            'exim' => $logs['exim'] ?? '',
            'dovecot' => $logs['dovecot'] ?? '',
            'lfd_history' => $logs['lfd_history'] ?? '',
        ];

        if (! empty($logs['ssh_errors'])) {
            $completeLogsStructure['ssh_errors'] = $logs['ssh_errors'];
        }

        $finalResult = FirewallAnalysisResult::combine(...$results);

        return new FirewallAnalysisResult(
            $wasBlocked,
            $completeLogsStructure
        );
    }

    public function unblock(string $ip, string $sshKeyName): void
    {
        $this->firewallService->checkProblems($this->host, $sshKeyName, 'unblock', $ip);
    }

    /**
     * Add IP to whitelist for 24 hours
     *
     * @param  string  $ip  IP address to whitelist
     * @param  string  $sshKeyName  SSH key name for authentication
     *
     * @phpstan-ignore method.unused
     */
    private function addToWhitelist(string $ip, string $sshKeyName): void
    {
        // Add IP to whitelist for 24 hours (86400 seconds)
        $this->firewallService->checkProblems($this->host, $sshKeyName, 'whitelist', $ip);
    }

    public function supports(string $panelType): bool
    {
        return $panelType === self::PANEL_TYPE;
    }

    /**
     * @param  array<string, bool>  $services
     */
    public function withServiceChecks(array $services): self
    {
        return new self($this->firewallService, $this->host, array_merge($this->serviceChecks, $services));
    }

    private function analyzeCsfOutput(string $output): FirewallAnalysisResult
    {
        // Buscar bloqueo en csf.deny, DROP, DENYIN, Temporary Blocks o cualquier forma de DENY
        $isBlocked = str_contains($output, 'csf.deny:') ||
                    str_contains($output, 'DROP') ||
                    str_contains($output, 'DENYIN') ||
                    str_contains($output, 'Temporary Blocks') ||
                    str_contains($output, 'DENY') ||
                    str_contains($output, 'deny');

        return new FirewallAnalysisResult($isBlocked, ['csf' => $output]);
    }

    private function analyzeEximOutput(string $output): FirewallAnalysisResult
    {
        // CRITICAL FIX: Exim logs show authentication failures, NOT firewall blocks
        // These are context logs only, not evidence of active blocking
        return new FirewallAnalysisResult(false, ['exim' => $output]);
    }

    private function analyzeDovecotOutput(string $output): FirewallAnalysisResult
    {
        // CRITICAL FIX: Dovecot logs show authentication failures, NOT firewall blocks
        // These are context logs only, not evidence of active blocking
        return new FirewallAnalysisResult(false, ['dovecot' => $output]);
    }

    /**
     * Check if output is an SSH error sentinel
     */
    private function isSshError(string $output): bool
    {
        return str_starts_with($output, '[SSH_ERROR:');
    }
}
