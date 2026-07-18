<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\{CheckRecentBlockHistoryAction, UnblockIpActionNormalMode};
use App\Actions\Firewall\ValidateUserAccessToHostAction;
use App\Actions\SimpleUnblock\{AnalyzeFirewallForIpAction, ValidateIpFormatAction};
use App\Exceptions\{ConnectionFailedException, FirewallException};
use App\Models\{Host, User};
use App\Services\{AuditService, FirewallConnectionErrorService, ReportGenerator};
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{DB, Log};
use InvalidArgumentException;
use Throwable;

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

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public int $timeout = 300;

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
     * Unique ID to coalesce duplicate dispatches for the same check request.
     *
     * Note: across retries of the SAME job instance this is irrelevant
     * (Laravel handles retries by the job id). It matters if the same
     * (user, host, ip) combination is dispatched concurrently from the UI.
     */
    public function uniqueId(): string
    {
        return "firewall_check:{$this->userId}:{$this->hostId}:{$this->ip}";
    }

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

                // 4b. Check recent block history for context
                $recentHistory = app(CheckRecentBlockHistoryAction::class)->handle($this->ip, $host->id);

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
                    $unblockResults,
                    $recentHistory
                );

                // 7. Audit the operation
                $auditService->logFirewallCheck($user, $host, $this->ip, $analysis->isBlocked());

                // 8. Dispatch notification job with copyUserId
                dispatch(new SendReportNotificationJob((string) $report->id, $this->copyUserId));

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

    /**
     * Handle the definitive job failure (after all retries are exhausted).
     *
     * The happy path notifies through SendReportNotificationJob, but a connection
     * failure rolls back the wrapping DB::transaction before any notification is
     * persisted, leaving the requester and admin with no feedback at all. When the
     * root cause is an unreachable host, notify both — mirroring the SimpleUnblock
     * flow, which already alerts on failure.
     */
    public function failed(?Throwable $exception): void
    {
        $connectionError = $this->findConnectionFailure($exception);

        // Only the unreachable-host path is handled here. Other failures keep the
        // existing audit/log behaviour to avoid sending a misleading SSH-connection
        // alert for unrelated errors.
        if ($connectionError === null) {
            return;
        }

        $user = User::find($this->userId);
        $host = Host::find($this->hostId);

        if ($user === null || $host === null) {
            Log::warning('Cannot notify firewall connection failure: user or host not found', [
                'user_id' => $this->userId,
                'host_id' => $this->hostId,
                'ip' => $this->ip,
            ]);

            return;
        }

        app(FirewallConnectionErrorService::class)->handleConnectionError(
            $this->ip,
            $host,
            $user,
            $connectionError->getMessage(),
            $connectionError,
        );
    }

    /**
     * Walk the exception chain looking for the underlying connection failure.
     */
    private function findConnectionFailure(?Throwable $exception): ?ConnectionFailedException
    {
        while ($exception !== null) {
            if ($exception instanceof ConnectionFailedException) {
                return $exception;
            }

            $exception = $exception->getPrevious();
        }

        return null;
    }
}
