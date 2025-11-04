<?php

declare(strict_types=1);

namespace App\Actions\SimpleUnblock;

use App\Jobs\SendSimpleUnblockNotificationJob;
use App\Models\{Host, Report};
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Notify Simple Unblock Result Action
 *
 * Orchestrates email notifications based on unblock decision.
 * Dispatches appropriate notification jobs for user and/or admin.
 */
class NotifySimpleUnblockResultAction
{
    use AsAction;

    /**
     * Send notifications based on unblock decision
     */
    public function handle(
        UnblockDecision $decision,
        string $email,
        string $domain,
        ?Report $report,
        Host $host,
        ?array $analysisData = null
    ): void {
        Log::info('Sending notifications for simple unblock', [
            'decision' => $decision->reason,
            'notify_user' => $decision->notifyUser,
            'notify_admin' => $decision->notifyAdmin,
            'domain' => $domain,
        ]);

        if ($decision->shouldUnblock) {
            // Success case: Notify both user and admin
            $this->notifySuccess($email, $domain, $report);
        } elseif ($decision->notifyUser) {
            // No unblock needed but user is notified (valid domain, not blocked)
            $this->notifyNoUnblockNeeded($email, $domain, $report, $decision->reason);
        } else {
            // Suspicious/Invalid: Notify admin only
            $this->notifyAdminOnly($email, $domain, $decision->reason, $host, $analysisData);
        }
    }

    /**
     * Notify user and admin about successful unblock
     */
    private function notifySuccess(string $email, string $domain, ?Report $report): void
    {
        if (! $report) {
            Log::warning('Cannot send success notification without report');

            return;
        }

        SendSimpleUnblockNotificationJob::dispatch(
            reportId: (string) $report->id,
            email: $email,
            domain: $domain,
            adminOnly: false
        );

        Log::info('Success notification dispatched', [
            'report_id' => $report->id,
            'email' => $email,
        ]);
    }

    /**
     * Notify user that no unblock was needed (IP not blocked)
     */
    private function notifyNoUnblockNeeded(string $email, string $domain, ?Report $report, string $reason): void
    {
        if (! $report) {
            Log::warning('Cannot send no-unblock-needed notification without report');

            return;
        }

        SendSimpleUnblockNotificationJob::dispatch(
            reportId: (string) $report->id,
            email: $email,
            domain: $domain,
            adminOnly: false,
            reason: $reason
        );

        Log::info('No-unblock-needed notification dispatched to user', [
            'report_id' => $report->id,
            'email' => $email,
            'reason' => $reason,
        ]);
    }

    /**
     * Notify admin only (silent from user perspective)
     */
    private function notifyAdminOnly(
        string $email,
        string $domain,
        string $reason,
        Host $host,
        ?array $analysisData = null
    ): void {
        SendSimpleUnblockNotificationJob::dispatch(
            reportId: null,
            email: $email,
            domain: $domain,
            adminOnly: true,
            reason: $reason,
            hostFqdn: $host->fqdn,
            analysisData: $analysisData
        );

        Log::info('Admin-only notification dispatched', [
            'email' => $email,
            'domain' => $domain,
            'reason' => $reason,
        ]);
    }

    /**
     * Handle suspicious attempt (domain not in database)
     */
    public function handleSuspiciousAttempt(
        string $ip,
        string $domain,
        string $email,
        Host $host,
        string $reason
    ): void {
        Log::warning('Suspicious simple unblock attempt detected', [
            'ip' => $ip,
            'domain' => $domain,
            'email' => $email,
            'host_fqdn' => $host->fqdn,
            'reason' => $reason,
        ]);

        SendSimpleUnblockNotificationJob::dispatch(
            reportId: null,
            email: $email,
            domain: $domain,
            adminOnly: true,
            reason: $reason,
            hostFqdn: $host->fqdn,
            analysisData: [
                'ip' => $ip,
                'warning' => 'Possible abuse attempt - domain validation failed',
                'validation_reason' => $reason,
                'timestamp' => now()->toISOString(),
            ]
        );
    }
}
