<?php

declare(strict_types=1);

namespace App\Services\Firewall\V2;

use App\Exceptions\{FirewallException, InvalidIpException};
use App\Jobs\SendReportNotificationJob;
use App\Models\{Host, Report, User};
use App\Services\{AuditService, ReportGenerator};
use App\Services\Firewall\FirewallAnalysisResult;
use Exception;
use Illuminate\Support\Facades\{DB, Log};
use InvalidArgumentException;

/**
 * Firewall Orchestrator V2 - Orchestrator Pattern (SOLID Compliant)
 *
 * Orquesta el proceso completo de análisis y desbloqueo de firewall:
 * 1. Valida parámetros de entrada
 * 2. Delega análisis al FirewallLogAnalyzer
 * 3. Delega desbloqueo al FirewallUnblocker
 * 4. Genera reportes
 * 5. Coordina notificaciones
 *
 * Responsabilidad única: Orquestar - no hace el trabajo, delega
 */
class FirewallOrchestrator
{
    public function __construct(
        private FirewallLogAnalyzer $logAnalyzer,
        private FirewallUnblocker $unblocker,
        private ReportGenerator $reportGenerator,
        private AuditService $auditService
    ) {}

    /**
     * Execute complete firewall check and unblock process
     * (Compatible con la interfaz actual)
     */
    public function executeFirewallCheck(string $ipAddress, int $userId, int $hostId): Report
    {
        // Validate and load models
        $user = $this->loadUser($userId);
        $host = $this->loadHost($hostId);

        // Validate IP address
        $this->validateIpAddress($ipAddress);

        // Log the start of the operation
        Log::info('Starting V2 firewall check process', [
            'ip_address' => $ipAddress,
            'user_id' => $userId,
            'host_id' => $hostId,
            'host_fqdn' => $host->fqdn,
            'host_panel' => $host->panel,
        ]);

        try {
            return DB::transaction(function () use ($ipAddress, $user, $host) {
                // 1. Perform firewall analysis (delegated to analyzer)
                $analysisResult = $this->performAnalysis($ipAddress, $host);

                // 2. Perform unblock if IP is blocked (delegated to unblocker)
                $unblockResults = null;
                if ($analysisResult->isBlocked()) {
                    $unblockResults = $this->performUnblock($ipAddress, $host);
                }

                // 3. Generate comprehensive report (delegated to generator)
                $report = $this->reportGenerator->generateReport(
                    $ipAddress,
                    $user,
                    $host,
                    $analysisResult,
                    $unblockResults
                );

                // 4. Audit the operation (delegated to audit service)
                $this->auditService->logFirewallCheck($user, $host, $ipAddress, $analysisResult->isBlocked());

                // 5. Dispatch notification job (expects string UUID)
                SendReportNotificationJob::dispatch((string) $report->id);

                Log::info('V2 firewall check process completed successfully', [
                    'report_id' => $report->id,
                    'ip_address' => $ipAddress,
                    'was_blocked' => $analysisResult->isBlocked(),
                    'unblock_performed' => $unblockResults !== null,
                ]);

                return $report;
            });

        } catch (Exception $e) {
            Log::error('V2 firewall check process failed', [
                'ip_address' => $ipAddress,
                'user_id' => $user->id,
                'host_id' => $host->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Audit the failure
            $this->auditService->logFirewallCheckFailure($user, $host, $ipAddress, $e->getMessage());

            throw new FirewallException(
                "V2 Firewall check failed for IP {$ipAddress} on host {$host->fqdn}: ".$e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Perform firewall log analysis
     * (Delegated responsibility)
     */
    private function performAnalysis(string $ipAddress, Host $host): FirewallAnalysisResult
    {
        Log::info('Starting firewall log analysis', [
            'ip_address' => $ipAddress,
            'host_fqdn' => $host->fqdn,
            'host_panel' => $host->panel,
        ]);

        $analysisResult = match ($host->panel) {
            'directadmin' => $this->logAnalyzer->analyzeDirectAdmin($ipAddress, $host),
            'cpanel' => throw new FirewallException('cPanel analysis not yet implemented'),
            default => throw new FirewallException("Unsupported panel type: {$host->panel}")
        };

        Log::info('Firewall log analysis completed', [
            'ip_address' => $ipAddress,
            'host_fqdn' => $host->fqdn,
            'blocked' => $analysisResult->isBlocked(),
            'logs_collected' => count($analysisResult->getLogs()),
        ]);

        return $analysisResult;
    }

    /**
     * Perform unblock operations
     * (Delegated responsibility)
     */
    private function performUnblock(string $ipAddress, Host $host): array
    {
        Log::info('Starting unblock operations', [
            'ip_address' => $ipAddress,
            'host_fqdn' => $host->fqdn,
            'host_panel' => $host->panel,
        ]);

        $unblockResults = $this->unblocker->performCompleteUnblock($ipAddress, $host);
        $status = $this->unblocker->getUnblockStatus($unblockResults);

        Log::info('Unblock operations completed', [
            'ip_address' => $ipAddress,
            'host_fqdn' => $host->fqdn,
            'overall_success' => $status['overall_success'],
            'operations' => $status['operations_performed'],
        ]);

        return $unblockResults;
    }

    /**
     * Load and validate user
     */
    private function loadUser(int $userId): User
    {
        $user = User::find($userId);

        if (! $user) {
            throw new InvalidArgumentException("User with ID {$userId} not found");
        }

        return $user;
    }

    /**
     * Load and validate host
     */
    private function loadHost(int $hostId): Host
    {
        $host = Host::find($hostId);

        if (! $host) {
            throw new InvalidArgumentException("Host with ID {$hostId} not found");
        }

        return $host;
    }

    /**
     * Validate IP address format
     */
    private function validateIpAddress(string $ipAddress): void
    {
        if (! filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new InvalidIpException("Invalid IP address format: {$ipAddress}");
        }
    }

    /**
     * Get service health status for monitoring
     */
    public function getHealthStatus(): array
    {
        return [
            'status' => 'healthy',
            'components' => [
                'log_analyzer' => 'operational',
                'unblocker' => 'operational',
                'report_generator' => 'operational',
                'audit_service' => 'operational',
            ],
            'timestamp' => now()->toISOString(),
        ];
    }
}
