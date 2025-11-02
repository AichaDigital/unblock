<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\SimpleUnblock\{
    AnalyzeFirewallForIpAction,
    CheckIpInServerLogsAction,
    CreateSimpleUnblockReportAction,
    EvaluateUnblockMatchAction,
    NotifySimpleUnblockResultAction,
    ValidateDomainInDatabaseAction,
    ValidateIpFormatAction
};
use App\Actions\UnblockIpAction;
use App\Models\Host;
use App\Services\{AnonymousUserService, SshConnectionManager};
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{Cache, Log};

/**
 * Process Simple Unblock Job (Refactored v2.0 - SOLID Compliant)
 *
 * Handles anonymous IP unblocking requests with domain validation.
 * This job is part of the decoupled "simple mode" architecture.
 *
 * RESPONSIBILITIES (ONLY):
 * - Serialization of job data for queue
 * - Orchestration of atomic actions
 * - Lock management for duplicate prevention
 * - High-level error handling
 *
 * DOES NOT CONTAIN:
 * - Business logic (moved to Actions)
 * - Validation logic (moved to Actions)
 * - SSH operations (handled by Actions)
 * - Decision logic (moved to EvaluateUnblockMatchAction)
 *
 * Flow:
 * 1. Check if already processed (lock)
 * 2. Validate IP format
 * 3. Validate domain in database
 * 4. Analyze firewall status
 * 5. Check domain in server logs
 * 6. Evaluate unblock decision
 * 7. Execute unblock if needed
 * 8. Create report
 * 9. Send notifications
 */
class ProcessSimpleUnblockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $ip,
        public string $domain,
        public string $email,
        public int $hostId
    ) {}

    /**
     * Execute the job (Refactored - SOLID Compliant)
     *
     * Orchestrates atomic actions to process simple unblock request.
     * NO business logic here - only action coordination.
     */
    public function handle(
        ValidateIpFormatAction $validateIp,
        ValidateDomainInDatabaseAction $validateDomain,
        AnalyzeFirewallForIpAction $analyzeFirewall,
        CheckIpInServerLogsAction $checkLogs,
        EvaluateUnblockMatchAction $evaluateMatch,
        UnblockIpAction $unblockIp,
        CreateSimpleUnblockReportAction $createReport,
        NotifySimpleUnblockResultAction $notify,
        SshConnectionManager $sshManager
    ): void {
        // 1. Early abort if already processed (prevent duplicate processing)
        if ($this->isAlreadyProcessed()) {
            Log::info('Simple unblock already processed by another job', [
                'ip' => $this->ip,
                'domain' => $this->domain,
                'host_id' => $this->hostId,
            ]);

            return;
        }

        Log::info('Starting simple unblock job (v2.0 SOLID)', [
            'ip' => $this->ip,
            'domain' => $this->domain,
            'email' => $this->email,
            'host_id' => $this->hostId,
        ]);

        try {
            // 2. Load host
            $host = Host::findOrFail($this->hostId);

            // 3. Validate IP format
            $validateIp->handle($this->ip);

            // 4. CRITICAL: Validate domain in database BEFORE SSH operations
            $domainValidation = $validateDomain->handle($this->domain, $host->id);
            if (! $domainValidation->exists) {
                Log::warning('Simple unblock: Domain validation failed - ABORT', [
                    'ip' => $this->ip,
                    'domain' => $this->domain,
                    'reason' => $domainValidation->reason,
                    'host_fqdn' => $host->fqdn,
                ]);

                $notify->handleSuspiciousAttempt(
                    $this->ip,
                    $this->domain,
                    $this->email,
                    $host,
                    $domainValidation->reason
                );

                return; // ABORT
            }

            // 5. Generate SSH key file (PATH, not content)
            $keyPath = $sshManager->generateSshKey($host->hash);

            try {
                // 6. Analyze firewall status
                $analysis = $analyzeFirewall->handle($this->ip, $host);

                // 7. Check IP in server logs (CORRECTED: use key PATH)
                $logsSearch = $checkLogs->handle($host, $keyPath, $this->ip, $this->domain);

                // 8. Evaluate unblock decision (CRITICAL BUSINESS LOGIC)
                $decision = $evaluateMatch->handle(
                    $analysis->isBlocked(),
                    $logsSearch->foundInLogs,
                    $domainValidation->exists
                );

                Log::info('Unblock decision made', [
                    'decision' => $decision->reason,
                    'should_unblock' => $decision->shouldUnblock,
                ]);

                // 9. Execute unblock if decision is positive (CORRECTED: use key PATH)
                $unblockResults = null;
                if ($decision->shouldUnblock) {
                    $unblockResults = $unblockIp->handle($this->ip, $host->id, $keyPath);
                    $this->markAsProcessed(); // Lock to prevent duplicates
                }
            } finally {
                // CRITICAL: Always cleanup SSH key file
                $sshManager->removeSshKey($keyPath);
            }

            // 9. Create report for audit trail
            $report = $createReport->handle(
                $this->ip,
                $this->domain,
                $this->email,
                $host,
                $analysis,
                $unblockResults,
                $decision
            );

            // 10. Send appropriate notifications
            $notify->handle(
                $decision,
                $this->email,
                $this->domain,
                $report,
                $host,
                [
                    'ip' => $this->ip,
                    'was_blocked' => $analysis->isBlocked(),
                    'logs_preview' => substr(json_encode($analysis->getLogs()), 0, 500),
                ]
            );

            // 11. Log audit trail
            $this->logAuditTrail($host, $decision, $report);

            Log::info('Simple unblock job completed successfully', [
                'report_id' => $report->id,
                'decision' => $decision->reason,
            ]);

        } catch (Exception $e) {
            $this->handleJobFailure($e, $notify);
            throw $e;
        }
    }

    /**
     * Check if this request was already processed by another job
     */
    private function isAlreadyProcessed(): bool
    {
        $lockKey = "simple_unblock_processed:{$this->ip}:{$this->domain}";

        return Cache::has($lockKey);
    }

    /**
     * Mark request as processed to prevent duplicate processing
     */
    private function markAsProcessed(): void
    {
        $lockKey = "simple_unblock_processed:{$this->ip}:{$this->domain}";
        Cache::put($lockKey, true, now()->addMinutes(10));
    }

    /**
     * Log audit trail for simple unblock operation
     */
    private function logAuditTrail($host, $decision, $report): void
    {
        activity()
            ->withProperties([
                'ip' => $this->ip,
                'domain' => $this->domain,
                'email' => $this->email,
                'host_id' => $host->id,
                'host_fqdn' => $host->fqdn,
                'report_id' => $report->id,
                'decision' => $decision->reason,
                'unblocked' => $decision->shouldUnblock,
            ])
            ->log($decision->shouldUnblock ? 'simple_unblock_success' : 'simple_unblock_no_match');
    }

    /**
     * Handle job failure - create error report and notify admin
     */
    private function handleJobFailure(Exception $e, NotifySimpleUnblockResultAction $notify): void
    {
        Log::error('Simple unblock job failed', [
            'ip' => $this->ip,
            'domain' => $this->domain,
            'email' => $this->email,
            'host_id' => $this->hostId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Create error report for audit trail
        try {
            $host = Host::find($this->hostId);
            if ($host) {
                \App\Models\Report::create([
                    'ip' => $this->ip,
                    'user_id' => AnonymousUserService::get()->id,
                    'host_id' => $this->hostId,
                    'analysis' => [
                        'error' => true,
                        'error_message' => $e->getMessage(),
                        'domain' => $this->domain,
                        'email' => $this->email,
                        'simple_mode' => true,
                        'unblock_performed' => false,
                        'analysis_timestamp' => now()->toISOString(),
                    ],
                    'logs' => [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ],
                ]);

                // Notify admin
                SendSimpleUnblockNotificationJob::dispatch(
                    reportId: null,
                    email: $this->email,
                    domain: $this->domain,
                    adminOnly: true,
                    reason: 'job_failure',
                    hostFqdn: $host->fqdn,
                    analysisData: [
                        'ip' => $this->ip,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        } catch (Exception $reportError) {
            Log::error('Failed to create error report', [
                'original_error' => $e->getMessage(),
                'report_error' => $reportError->getMessage(),
            ]);
        }
    }
}
