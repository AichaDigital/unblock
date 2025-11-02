<?php

declare(strict_types=1);

namespace App\Services\Firewall;

use App\Models\Host;
use App\Services\FirewallService;

readonly class CpanelFirewallAnalyzer implements FirewallAnalyzerInterface
{
    private const PANEL_TYPE = 'cpanel';

    private array $serviceChecks;

    public function __construct(
        private FirewallService $firewallService,
        private Host $host,
        ?array $serviceChecks = null
    ) {
        $this->serviceChecks = $serviceChecks ?? [
            'csf' => true,
            'exim_cpanel' => true,
            'dovecot_cpanel' => true,
        ];
    }

    public function analyze(string $ipAddress, mixed $session): FirewallAnalysisResult
    {
        // Extraer SSH key path del session (compatible con ambas implementaciones)
        $sshKeyName = method_exists($session, 'getSshKeyPath')
            ? $session->getSshKeyPath()
            : (string) $session; // fallback para compatibilidad

        $results = [];
        $logs = [];
        $wasBlocked = false;

        // Primero verificamos si la IP está bloqueada en CSF
        if (($this->serviceChecks['csf'] ?? false) === true) {
            $csfOutput = $this->firewallService->checkProblems($this->host, $sshKeyName, 'csf', $ipAddress);
            $logs['csf'] = $csfOutput;
            $csfResult = $this->analyzeCsfOutput($csfOutput);
            $results[] = $csfResult;
            if ($csfResult->isBlocked()) {
                $wasBlocked = true;
            }
        }

        // Solo si la IP está bloqueada, procedemos a buscar en los logs
        if ($wasBlocked === true) {
            // Verificar Exim
            if (($this->serviceChecks['exim_cpanel'] ?? false) === true) {
                $eximOutput = $this->firewallService->checkProblems($this->host, $sshKeyName, 'exim_cpanel', $ipAddress);
                $logs['exim'] = $eximOutput;
                $results[] = $this->analyzeEximOutput($eximOutput);
            }

            // Verificar Dovecot
            if (($this->serviceChecks['dovecot_cpanel'] ?? false) === true) {
                $dovecotOutput = $this->firewallService->checkProblems($this->host, $sshKeyName, 'dovecot_cpanel', $ipAddress);
                $logs['dovecot'] = $dovecotOutput;
                $results[] = $this->analyzeDovecotOutput($dovecotOutput);
            }

            // REMOVED: Auto-unblock logic - this must be done by the caller based on complete validation
            // The analyzer should ONLY analyze and report, NOT make unblock decisions
        }

        return FirewallAnalysisResult::combine(...$results);
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
}
