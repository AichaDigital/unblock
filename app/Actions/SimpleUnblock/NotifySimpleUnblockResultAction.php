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
        Log::info('Dispatching notification for simple unblock result.', [
            'decision' => $decision->reason,
            'should_unblock' => $decision->shouldUnblock,
            'domain' => $domain,
            'report_id' => $report?->id,
        ]);

        // Per business rules, always notify both user and admin of the investigation result.
        SendSimpleUnblockNotificationJob::dispatch(
            reportId: (string) $report?->id,
            email: $email,
            domain: $domain,
            // The `adminOnly` flag is removed to ensure user is always notified.
            // The Job will handle sending a copy to the admin.
            reason: $decision->reason,
            hostFqdn: $host->fqdn,
            analysisData: $analysisData
        );
    }

    /**
     * REVIEW: This handles a pre-check validation failure.
     * It correctly notifies only the admin of a potential abuse attempt.
     * This logic is separate from the main investigation flow.
     *
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
            adminOnly: true, // This is a special case for admin-only alerts.
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
