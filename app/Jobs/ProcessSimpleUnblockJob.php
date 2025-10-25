<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\{ConnectionFailedException, InvalidIpException};
use App\Models\{Host, Report};
use App\Services\{AnonymousUserService, FirewallUnblocker, SshConnectionManager};
use App\Services\Firewall\{FirewallAnalysisResult, FirewallAnalyzerFactory};
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{Cache, Log};
use InvalidArgumentException;

/**
 * Process Simple Unblock Job
 *
 * Handles anonymous IP unblocking requests with domain validation.
 * This job is part of the decoupled "simple mode" architecture.
 *
 * Flow:
 * 1. Analyze firewall on specified host
 * 2. Check if domain exists in server logs (Apache/Nginx/Exim)
 * 3. If IP blocked + domain matches: Unblock + notify user + admin
 * 4. If only IP blocked or only domain found: Silent log admin only
 * 5. If nothing found: Silent log admin
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
     * Execute the job.
     */
    public function handle(
        SshConnectionManager $sshManager,
        FirewallAnalyzerFactory $analyzerFactory,
        FirewallUnblocker $unblocker
    ): void {
        // Check if another job already found a match and processed it
        $lockKey = "simple_unblock_processed:{$this->ip}:{$this->domain}";
        if (Cache::has($lockKey)) {
            Log::info('Simple unblock already processed by another job', [
                'ip' => $this->ip,
                'domain' => $this->domain,
                'host_id' => $this->hostId,
            ]);

            return;
        }

        Log::info('Starting simple unblock job', [
            'ip' => $this->ip,
            'domain' => $this->domain,
            'email' => $this->email,
            'host_id' => $this->hostId,
        ]);

        try {
            $host = $this->loadHost($this->hostId);
            $this->validateIpAddress($this->ip);

            // 1. Perform firewall analysis
            $analysisResult = $this->performFirewallAnalysis($this->ip, $host, $sshManager, $analyzerFactory);
            $ipIsBlocked = $analysisResult->isBlocked();

            // 2. Check if domain exists in server logs
            $domainExistsOnHost = $this->checkDomainInServerLogs($this->ip, $this->domain, $host, $sshManager);

            // 3. Evaluate match
            if ($ipIsBlocked && $domainExistsOnHost) {
                // FULL MATCH: Unblock + notify user + admin
                $this->handleFullMatch($this->ip, $this->domain, $this->email, $host, $analysisResult, $unblocker);

                // Set lock to prevent other jobs from processing
                Cache::put($lockKey, true, now()->addMinutes(10));
            } elseif ($ipIsBlocked || $domainExistsOnHost) {
                // PARTIAL MATCH: Silent log admin only (possible abuse)
                $reason = $ipIsBlocked ? 'ip_blocked_but_domain_not_found' : 'domain_found_but_ip_not_blocked';
                $this->logSilentAttempt($this->ip, $this->domain, $this->email, $host, $reason, $analysisResult);
            } else {
                // NO MATCH: Silent log admin
                $this->logSilentAttempt($this->ip, $this->domain, $this->email, $host, 'no_match_found', $analysisResult);
            }

        } catch (Exception $e) {
            Log::error('Simple unblock job failed', [
                'ip' => $this->ip,
                'domain' => $this->domain,
                'email' => $this->email,
                'host_id' => $this->hostId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Notify admin about the failure
            $this->notifyAdminFailure($this->ip, $this->domain, $this->email, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Perform firewall analysis on the host
     */
    private function performFirewallAnalysis(
        string $ip,
        Host $host,
        SshConnectionManager $sshManager,
        FirewallAnalyzerFactory $analyzerFactory
    ): FirewallAnalysisResult {
        $session = $sshManager->createSession($host);

        try {
            $analyzer = $analyzerFactory->createForHost($host);
            $analysisResult = $analyzer->analyze($ip, $session);

            Log::info('Firewall analysis completed in simple unblock job', [
                'ip' => $ip,
                'host_fqdn' => $host->fqdn,
                'blocked' => $analysisResult->isBlocked(),
            ]);

            return $analysisResult;
        } finally {
            $session->cleanup();
        }
    }

    /**
     * Check if domain exists in server logs (Apache/Nginx/Exim)
     */
    private function checkDomainInServerLogs(
        string $ip,
        string $domain,
        Host $host,
        SshConnectionManager $sshManager
    ): bool {
        $session = $sshManager->createSession($host);

        try {
            // Build composite grep command to search in multiple log sources
            $commands = $this->buildDomainSearchCommands($ip, $domain, $host->panel);
            $combinedCommand = implode(' || ', $commands);

            Log::debug('Searching for domain in server logs', [
                'ip' => $ip,
                'domain' => $domain,
                'host_fqdn' => $host->fqdn,
                'panel' => $host->panel,
            ]);

            $result = $session->execute($combinedCommand);
            $found = ! empty(trim($result));

            Log::info('Domain search completed', [
                'ip' => $ip,
                'domain' => $domain,
                'host_fqdn' => $host->fqdn,
                'found' => $found,
                'result_preview' => substr($result, 0, 200),
            ]);

            return $found;

        } catch (ConnectionFailedException $e) {
            Log::warning('Could not check domain in logs (connection failed)', [
                'ip' => $ip,
                'domain' => $domain,
                'host_id' => $host->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        } finally {
            $session->cleanup();
        }
    }

    /**
     * Build domain search commands based on panel type
     */
    private function buildDomainSearchCommands(string $ip, string $domain, string $panelType): array
    {
        $ipEscaped = escapeshellarg($ip);
        $domainEscaped = escapeshellarg($domain);

        $commands = [
            // Apache access logs (last 7 days)
            "find /var/log/apache2 -name 'access.log*' -mtime -7 -type f -exec grep -l {$ipEscaped} {} \\; 2>/dev/null | xargs grep -i {$domainEscaped} 2>/dev/null | head -1",

            // Nginx access logs (last 7 days)
            "find /var/log/nginx -name 'access.log*' -mtime -7 -type f -exec grep -l {$ipEscaped} {} \\; 2>/dev/null | xargs grep -i {$domainEscaped} 2>/dev/null | head -1",

            // Exim mail logs (last 7 days)
            "find /var/log/exim -name 'mainlog*' -mtime -7 -type f -exec grep -l {$ipEscaped} {} \\; 2>/dev/null | xargs grep -i {$domainEscaped} 2>/dev/null | head -1",
        ];

        if ($panelType === 'cpanel') {
            // cPanel-specific: domlogs
            $commands[] = "find /usr/local/apache/domlogs -name '*{$domain}*' -mtime -7 -type f -exec grep {$ipEscaped} {} \\; 2>/dev/null | head -1";
        }

        return $commands;
    }

    /**
     * Handle full match: unblock IP and notify user + admin
     */
    private function handleFullMatch(
        string $ip,
        string $domain,
        string $email,
        Host $host,
        FirewallAnalysisResult $analysisResult,
        FirewallUnblocker $unblocker
    ): void {
        Log::info('Simple unblock: FULL MATCH - proceeding with unblock', [
            'ip' => $ip,
            'domain' => $domain,
            'email' => $email,
            'host_fqdn' => $host->fqdn,
        ]);

        // Perform unblock operations
        $unblockResults = $unblocker->unblockIp($ip, $host, $analysisResult);

        // Create report (using anonymous system user)
        $report = Report::create([
            'ip' => $ip,
            'user_id' => AnonymousUserService::get()->id,
            'host_id' => $host->id,
            'analysis' => [
                'was_blocked' => true,
                'domain' => $domain,
                'email' => $email,
                'simple_mode' => true,
                'unblock_performed' => true,
                'unblock_status' => $unblockResults,
                'analysis_timestamp' => now()->toISOString(),
            ],
            'logs' => $analysisResult->getLogs(),
        ]);

        // Dispatch notification job
        SendSimpleUnblockNotificationJob::dispatch(
            reportId: (string) $report->id,
            email: $email,
            domain: $domain
        );

        // Log audit trail
        activity()
            ->withProperties([
                'ip' => $ip,
                'domain' => $domain,
                'email' => $email,
                'host_id' => $host->id,
                'host_fqdn' => $host->fqdn,
                'report_id' => $report->id,
                'result' => 'unblocked',
            ])
            ->log('simple_unblock_success');
    }

    /**
     * Log silent attempt (admin notification only)
     */
    private function logSilentAttempt(
        string $ip,
        string $domain,
        string $email,
        Host $host,
        string $reason,
        FirewallAnalysisResult $analysisResult
    ): void {
        Log::info('Simple unblock: Silent log (no user notification)', [
            'ip' => $ip,
            'domain' => $domain,
            'email' => $email,
            'host_fqdn' => $host->fqdn,
            'reason' => $reason,
        ]);

        // Send admin-only notification
        SendSimpleUnblockNotificationJob::dispatch(
            reportId: null,
            email: $email,
            domain: $domain,
            adminOnly: true,
            reason: $reason,
            hostFqdn: $host->fqdn,
            analysisData: [
                'ip' => $ip,
                'was_blocked' => $analysisResult->isBlocked(),
                'logs_preview' => substr(json_encode($analysisResult->getLogs()), 0, 500),
            ]
        );

        // Audit log
        activity()
            ->withProperties([
                'ip' => $ip,
                'domain' => $domain,
                'email' => $email,
                'host_id' => $host->id,
                'host_fqdn' => $host->fqdn,
                'reason' => $reason,
                'ip_blocked' => $analysisResult->isBlocked(),
            ])
            ->log('simple_unblock_no_match');
    }

    /**
     * Notify admin about job failure
     */
    private function notifyAdminFailure(string $ip, string $domain, string $email, string $error): void
    {
        SendSimpleUnblockNotificationJob::dispatch(
            reportId: null,
            email: $email,
            domain: $domain,
            adminOnly: true,
            reason: 'job_failure',
            analysisData: [
                'ip' => $ip,
                'error' => $error,
            ]
        );
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
}
