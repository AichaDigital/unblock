<?php

declare(strict_types=1);

namespace App\Services\Firewall\V2;

use App\Models\Host;
use App\Services\Firewall\FirewallAnalysisResult;
use App\Services\{FirewallService, SshConnectionManager};
use App\Services\SshSession;
use Illuminate\Support\Facades\Log;

/**
 * Firewall Log Analyzer V2 - Single Responsibility Pattern
 *
 * Responsabilidad única: Analizar logs de firewall
 * Separado por modelo de panel (DirectAdmin, cPanel, etc.)
 * Compatible con la lógica actual de análisis
 */
class FirewallLogAnalyzer
{
    public function __construct(
        private FirewallService $firewallService,
        private SshConnectionManager $sshManager
    ) {}

    /**
     * Analyze firewall logs for a DirectAdmin host
     * (Mantiene la lógica actual del DirectAdminFirewallAnalyzer)
     */
    public function analyzeDirectAdmin(string $ipAddress, Host $host): FirewallAnalysisResult
    {
        $session = $this->sshManager->createSession($host);

        try {
            $logs = [];
            $results = [];
            $wasBlocked = false;

            // STEP 1: Análisis CSF primario (csf -g IP)
            $csfOutput = $this->firewallService->checkProblems($host, $session->getSshKeyPath(), 'csf', $ipAddress);
            $logs['csf'] = $csfOutput;
            $csfResult = $this->analyzeCsfOutput($csfOutput);
            $results[] = $csfResult;
            if ($csfResult->isBlocked()) {
                $wasBlocked = true;
            }

            // STEP 2: Análisis CSF profundo si no hay bloqueos primarios
            if (! $wasBlocked) {
                $deepResults = $this->performDeepCsfAnalysis($ipAddress, $host, $session, $logs);
                $results = array_merge($results, $deepResults['results']);
                if ($deepResults['blocked'] === true) {
                    $wasBlocked = true;
                }
                $logs = array_merge($logs, $deepResults['logs']);
            }

            // STEP 3: Análisis BFM independiente (siempre se ejecuta)
            $bfmResult = $this->analyzeBfmBlacklist($ipAddress, $host, $session);
            if ($bfmResult) {
                $logs['da_bfm_check'] = $bfmResult->getLogs()['da_bfm'] ?? '';
                $results[] = $bfmResult;
            }

            // STEP 4: Análisis de servicios adicionales
            $serviceResults = $this->analyzeAdditionalServices($ipAddress, $host, $session);
            $logs = array_merge($logs, $serviceResults['logs']);
            $results = array_merge($results, $serviceResults['results']);

            // Crear resultado combinado con todos los logs
            $combinedResult = FirewallAnalysisResult::combine(...$results);

            return new FirewallAnalysisResult(
                $combinedResult->isBlocked(),
                $logs
            );

        } finally {
            $session->cleanup();
        }
    }

    /**
     * Analyze CSF output for blocking patterns
     * (Mantiene la lógica actual)
     */
    private function analyzeCsfOutput(string $output): FirewallAnalysisResult
    {
        $blocked = $this->containsCsfBlocks($output);

        return new FirewallAnalysisResult(
            $blocked,
            ['csf' => $output]
        );
    }

    /**
     * Perform deep CSF analysis (deny files, temp files)
     * (Separada del análisis primario para claridad)
     */
    private function performDeepCsfAnalysis(string $ipAddress, Host $host, SshSession $session, array &$logs): array
    {
        $results = [];
        $blocked = false;
        $deepLogs = [];

        // Check csf.deny file (permanent blocks)
        $csfDenyOutput = $this->firewallService->checkProblems($host, $session->getSshKeyPath(), 'csf_deny_check', $ipAddress);
        if (! empty(trim($csfDenyOutput))) {
            $deepLogs['csf_deny'] = $csfDenyOutput;
            $csfDenyResult = $this->analyzeCsfDenyOutput($csfDenyOutput);
            $results[] = $csfDenyResult;
            if ($csfDenyResult->isBlocked()) {
                $blocked = true;
            }
        }

        // Check csf.tempip file (temporary blocks)
        if (! $blocked) {
            $csfTempOutput = $this->firewallService->checkProblems($host, $session->getSshKeyPath(), 'csf_tempip_check', $ipAddress);
            if (! empty(trim($csfTempOutput))) {
                $deepLogs['csf_tempip'] = $csfTempOutput;
                $csfTempResult = $this->analyzeCsfTempOutput($csfTempOutput);
                $results[] = $csfTempResult;
                if ($csfTempResult->isBlocked()) {
                    $blocked = true;
                }
            }
        }

        return [
            'results' => $results,
            'blocked' => $blocked,
            'logs' => $deepLogs,
        ];
    }

    /**
     * Analyze BFM blacklist independently
     * (Responsabilidad separada del análisis CSF)
     */
    private function analyzeBfmBlacklist(string $ipAddress, Host $host, SshSession $session): ?FirewallAnalysisResult
    {
        $bfmOutput = $this->firewallService->checkProblems($host, $session->getSshKeyPath(), 'da_bfm_check', $ipAddress);

        if (empty(trim($bfmOutput))) {
            return null;
        }

        $blocked = $this->containsBfmBlocks($bfmOutput, $ipAddress);

        return new FirewallAnalysisResult(
            $blocked,
            ['da_bfm' => $bfmOutput]
        );
    }

    /**
     * Analyze additional DirectAdmin services
     * (Separado por responsabilidad)
     */
    private function analyzeAdditionalServices(string $ipAddress, Host $host, SshSession $session): array
    {
        $services = [
            'exim_directadmin' => 'exim',
            'dovecot_directadmin' => 'dovecot',
            'mod_security_da' => 'mod_security',
        ];

        $results = [];
        $logs = [];

        foreach ($services as $serviceKey => $logKey) {
            $output = $this->firewallService->checkProblems($host, $session->getSshKeyPath(), $serviceKey, $ipAddress);
            $logs[$logKey] = $output;
            $results[] = $this->analyzeServiceOutput($serviceKey, $output);
        }

        return [
            'results' => $results,
            'logs' => $logs,
        ];
    }

    /**
     * Check if CSF output contains blocking patterns
     */
    private function containsCsfBlocks(string $output): bool
    {
        $blockPatterns = ['DENYIN', 'DENYOUT', 'Temporary Blocks'];

        foreach ($blockPatterns as $pattern) {
            if (str_contains($output, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if BFM output contains IP blocks
     */
    private function containsBfmBlocks(string $output, string $ipAddress): bool
    {
        if (empty(trim($output)) || str_contains($output, 'No matches')) {
            return false;
        }

        // Búsqueda exacta de IP en BFM (crítico para evitar falsos positivos)
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Verificar que la línea empiece con la IP exacta seguida de espacio o fin de línea
            if (preg_match('/^'.preg_quote($ipAddress, '/').'(\s|$)/', $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Analyze CSF deny file output
     */
    private function analyzeCsfDenyOutput(string $output): FirewallAnalysisResult
    {
        $blocked = ! empty(trim($output)) && ! str_contains($output, 'No matches');

        return new FirewallAnalysisResult(
            $blocked,
            ['csf_deny' => $output]
        );
    }

    /**
     * Analyze CSF temp file output
     */
    private function analyzeCsfTempOutput(string $output): FirewallAnalysisResult
    {
        $blocked = ! empty(trim($output)) && ! str_contains($output, 'No matches');

        return new FirewallAnalysisResult(
            $blocked,
            ['csf_tempip' => $output]
        );
    }

    /**
     * Analyze service-specific output
     */
    private function analyzeServiceOutput(string $service, string $output): FirewallAnalysisResult
    {
        // Por ahora, los servicios adicionales no determinan bloqueo
        // Solo proporcionan información de contexto
        return new FirewallAnalysisResult(
            false,
            [$service => $output]
        );
    }
}
