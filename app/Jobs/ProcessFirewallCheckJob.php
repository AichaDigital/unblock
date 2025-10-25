<?php

namespace App\Jobs;

use App\Exceptions\{FirewallException, InvalidIpException};
use App\Models\{Host, User};
use App\Services\{AuditService, FirewallUnblocker, ReportGenerator, SshConnectionManager};
use App\Services\Firewall\{FirewallAnalysisResult, FirewallAnalyzerFactory};
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{DB, Log};
use InvalidArgumentException;

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
     * Execute the job.
     */
    public function handle(
        SshConnectionManager $sshManager,
        FirewallAnalyzerFactory $analyzerFactory,
        FirewallUnblocker $unblocker,
        ReportGenerator $reportGenerator,
        AuditService $auditService
    ): void {
        Log::info('Starting firewall check job', [
            'ip_address' => $this->ip,
            'user_id' => $this->userId,
            'host_id' => $this->hostId,
        ]);

        try {
            // It's safe to re-load models inside the job.
            $user = $this->loadUser($this->userId);
            $host = $this->loadHost($this->hostId);

            // It's good practice to re-validate inside the job too.
            $this->validateIpAddress($this->ip);
            $this->validateUserAccess($user, $host);

            DB::transaction(function () use ($user, $host, $sshManager, $analyzerFactory, $unblocker, $reportGenerator, $auditService) {
                // 1. Perform firewall analysis
                $analysisResult = $this->performFirewallAnalysis($this->ip, $host, $sshManager, $analyzerFactory);

                // 2. Perform unblock if IP is blocked
                $unblockResults = null;
                if ($analysisResult->isBlocked()) {
                    $unblockResults = $this->performUnblockOperations($this->ip, $host, $analysisResult, $unblocker);
                }

                // 3. Generate comprehensive report
                $report = $reportGenerator->generateReport(
                    $this->ip,
                    $user,
                    $host,
                    $analysisResult,
                    $unblockResults
                );

                // 4. Audit the operation
                $auditService->logFirewallCheck($user, $host, $this->ip, $analysisResult->isBlocked());

                // 5. Manually dispatch notification job with copyUserId
                SendReportNotificationJob::dispatch((string) $report->id, $this->copyUserId);

                Log::info('Firewall check process completed successfully', [
                    'report_id' => $report->id,
                    'ip_address' => $this->ip,
                ]);
            });
        } catch (Exception $e) {
            Log::error('Firewall check job failed', [
                'ip_address' => $this->ip,
                'user_id' => $this->userId,
                'host_id' => $this->hostId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // We need to find the user again for auditing in case of failure before user load
            $userForAudit = User::find($this->userId);
            $hostForAudit = Host::find($this->hostId);
            if ($userForAudit && $hostForAudit) {
                $auditService->logFirewallCheckFailure($userForAudit, $hostForAudit, $this->ip, $e->getMessage());
            }

            // The exception will be logged by the queue worker.
            // We can re-throw it to make the job fail explicitly.
            throw new FirewallException(
                "Firewall check failed for IP {$this->ip} on host ID {$this->hostId}: ".$e->getMessage(),
                previous: $e
            );
        }
    }

    private function performFirewallAnalysis(string $ip, Host $host, SshConnectionManager $sshManager, FirewallAnalyzerFactory $analyzerFactory): FirewallAnalysisResult
    {
        $session = $sshManager->createSession($host);
        try {
            $analyzer = $analyzerFactory->createForHost($host);
            $analysisResult = $analyzer->analyze($ip, $session);
            Log::info('Firewall analysis completed in job', [
                'ip_address' => $ip,
                'host_fqdn' => $host->fqdn,
                'blocked' => $analysisResult->isBlocked(),
            ]);

            return $analysisResult;
        } finally {
            $session->cleanup();
        }
    }

    private function performUnblockOperations(string $ip, Host $host, FirewallAnalysisResult $analysisResult, FirewallUnblocker $unblocker): array
    {
        Log::info('Starting unblock operations in job', ['ip_address' => $ip, 'host_fqdn' => $host->fqdn]);
        $unblockResults = $unblocker->unblockIp($ip, $host, $analysisResult);
        Log::info('Unblock operations completed in job', ['ip_address' => $ip, 'host_fqdn' => $host->fqdn]);

        return $unblockResults;
    }

    private function loadUser(int $userId): User
    {
        $user = User::find($userId);
        if (! $user) {
            throw new InvalidArgumentException("User with ID {$userId} not found in job");
        }

        return $user;
    }

    private function loadHost(int $hostId): Host
    {
        $host = Host::find($hostId);
        if (! $host) {
            throw new InvalidArgumentException("Host with ID {$hostId} not found in job");
        }

        return $host;
    }

    private function validateIpAddress(string $ip): void
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidIpException("Invalid IP address format in job: {$ip}");
        }
    }

    private function validateUserAccess(User $user, Host $host): void
    {
        if ($user->is_admin) {
            return;
        }
        if (! $user->hasAccessToHost($host->id)) {
            throw new Exception("Access denied in job: User {$user->id} does not have permission for host {$host->id}");
        }
    }
}
