<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Firewall\ValidateUserAccessToHostAction;
use App\Actions\SimpleUnblock\{AnalyzeFirewallForIpAction, ValidateIpFormatAction};
use App\Actions\UnblockIpActionNormalMode;
use App\Exceptions\FirewallException;
use App\Models\{Host, User};
use App\Services\{AuditService, ReportGenerator};
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{DB, Log};
use InvalidArgumentException;

/**
 * Process Firewall Check Job (Refactored v2.0 - SOLID Compliant)
 *
 * Handles authenticated user IP unblocking requests (Normal Mode).
 * This is the standard flow for logged-in users with specific host access.
 *
 * RESPONSIBILITIES (ONLY):
 * - Serialization of job data for queue
 * - Orchestration of atomic actions
 * - Transaction management (DB)
 * - High-level error handling
 *
 * DOES NOT CONTAIN:
 * - Business logic (moved to Actions)
 * - Validation logic (moved to Actions)
 * - SSH operations (handled by Actions/Services)
 * - Access control logic (moved to Action)
 *
 * Differences with Simple Mode:
 * - User is authenticated (has User model)
 * - User selected specific host (no domain search needed)
 * - Access control validation required
 * - Report generated using authenticated user context
 * - No OTP verification
 *
 * Flow:
 * 1. Load user and host
 * 2. Validate IP format
 * 3. Validate user has access to host
 * 4. Analyze firewall status
 * 5. Execute unblock if IP blocked
 * 6. Generate comprehensive report
 * 7. Audit operation
 * 8. Send notifications
 */
class ProcessFirewallCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $ip,
        public int $userId,
        public int $hostId,
        public ?int $copyUserId = null
    ) {}

    /**
     * Execute the job (Refactored - SOLID Compliant)
     *
     * Orchestrates atomic actions to process firewall check.
     * NO business logic here - only action coordination.
     */
    public function handle(
        ValidateIpFormatAction $validateIp,
        ValidateUserAccessToHostAction $validateAccess,
        AnalyzeFirewallForIpAction $analyzeFirewall,
        UnblockIpActionNormalMode $unblockIp,
        ReportGenerator $reportGenerator,
        AuditService $auditService
    ): void {
        Log::info('Starting firewall check job (v2.0 SOLID)', [
            'ip_address' => $this->ip,
            'user_id' => $this->userId,
            'host_id' => $this->hostId,
        ]);

        try {
            DB::transaction(function () use (
                $validateIp,
                $validateAccess,
                $analyzeFirewall,
                $unblockIp,
                $reportGenerator,
                $auditService
            ) {
                // 1. Load models
                $user = $this->loadUser();
                $host = $this->loadHost();

                // 2. Validate IP format
                $validateIp->handle($this->ip);

                // 3. Validate user has access to host (Normal Mode specific)
                $validateAccess->handle($user, $host);

                // 4. Analyze firewall status
                $analysis = $analyzeFirewall->handle($this->ip, $host);

                // 5. Execute unblock if IP is blocked
                $unblockResults = null;
                if ($analysis->isBlocked()) {
                    Log::info('IP is blocked, proceeding with unblock', [
                        'ip' => $this->ip,
                        'host_fqdn' => $host->fqdn,
                    ]);

                    $unblockResults = $unblockIp->handle($this->ip, $host->id, $analysis);
                }

                // 6. Generate comprehensive report
                $report = $reportGenerator->generateReport(
                    $this->ip,
                    $user,
                    $host,
                    $analysis,
                    $unblockResults
                );

                // 7. Audit the operation
                $auditService->logFirewallCheck($user, $host, $this->ip, $analysis->isBlocked());

                // 8. Dispatch notification job with copyUserId
                SendReportNotificationJob::dispatch((string) $report->id, $this->copyUserId);

                Log::info('Firewall check process completed successfully', [
                    'report_id' => $report->id,
                    'ip_address' => $this->ip,
                    'was_blocked' => $analysis->isBlocked(),
                ]);
            });

        } catch (Exception $e) {
            $this->handleJobFailure($e, $auditService);
            throw new FirewallException(
                "Firewall check failed for IP {$this->ip} on host ID {$this->hostId}: ".$e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Load user from database
     */
    private function loadUser(): User
    {
        $user = User::find($this->userId);
        if (! $user) {
            throw new InvalidArgumentException("User with ID {$this->userId} not found in job");
        }

        return $user;
    }

    /**
     * Load host from database
     */
    private function loadHost(): Host
    {
        $host = Host::find($this->hostId);
        if (! $host) {
            throw new InvalidArgumentException("Host with ID {$this->hostId} not found in job");
        }

        return $host;
    }

    /**
     * Handle job failure - audit and log
     */
    private function handleJobFailure(Exception $e, AuditService $auditService): void
    {
        Log::error('Firewall check job failed', [
            'ip_address' => $this->ip,
            'user_id' => $this->userId,
            'host_id' => $this->hostId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Audit the failure
        try {
            $userForAudit = User::find($this->userId);
            $hostForAudit = Host::find($this->hostId);

            if ($userForAudit && $hostForAudit) {
                $auditService->logFirewallCheckFailure(
                    $userForAudit,
                    $hostForAudit,
                    $this->ip,
                    $e->getMessage()
                );
            }
        } catch (Exception $auditError) {
            Log::error('Failed to audit firewall check failure', [
                'original_error' => $e->getMessage(),
                'audit_error' => $auditError->getMessage(),
            ]);
        }
    }
}
