<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\SimpleUnblockNotificationMail;
use App\Models\Report;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\{Log, Mail};
use Throwable;

/**
 * Send Simple Unblock Notification Job
 *
 * Handles email notifications for anonymous simple unblock requests.
 * This is decoupled from the authenticated system's SendReportNotificationJob.
 *
 * Scenarios:
 * 1. Full match: Email to user + admin with report details
 * 2. No match / Partial match: Email to admin only (silent from user perspective)
 * 3. Job failure: Email to admin only
 */
class SendSimpleUnblockNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance
     */
    public function __construct(
        public readonly ?string $reportId,
        public readonly string $email,
        public readonly string $domain,
        public readonly ?string $reason = null,
        public readonly ?string $hostFqdn = null,
        public readonly ?array $analysisData = null,
        // This is kept for backward compatibility with handleSuspiciousAttempt,
        // but the main flow no longer uses it.
        public readonly bool $adminOnly = false
    ) {}

    /**
     * Execute the job
     */
    public function handle(): void
    {
        // Set locale for email translations
        app()->setLocale(config('app.locale'));

        try {
            // Special case for admin-only alerts (e.g., suspicious activity)
            if ($this->adminOnly) {
                $this->sendAdminAlert();

                return;
            }

            $report = $this->reportId ? Report::find($this->reportId) : null;
            if (! $report) {
                Log::warning('Report not found for simple unblock notification, sending admin alert instead.', [
                    'report_id' => $this->reportId,
                ]);
                $this->sendAdminAlert('report_not_found');

                return;
            }

            // Determine if the analysis was successful (we have a report with data)
            // isSuccess = true means we completed the analysis (regardless of block status)
            $wasBlocked = $report->analysis['was_blocked'] ?? false;
            $isSuccess = true; // We have a report, so analysis completed successfully

            Log::info('Sending simple unblock notification to user and admin', [
                'report_id' => $report->id,
                'email' => $this->email,
                'domain' => $this->domain,
                'analysis_completed' => true,
                'was_blocked' => $wasBlocked,
            ]);

            // 1. Send to user
            Mail::to($this->email)->send(
                new SimpleUnblockNotificationMail(
                    email: $this->email,
                    domain: $this->domain,
                    report: $report,
                    isSuccess: $isSuccess,
                    isAdminCopy: false,
                    wasBlocked: $wasBlocked
                )
            );

            // 2. Send copy to admin
            $adminEmail = config('unblock.admin_email');
            if ($adminEmail && $adminEmail !== $this->email) {
                Mail::to($adminEmail)->send(
                    new SimpleUnblockNotificationMail(
                        email: $this->email,
                        domain: $this->domain,
                        report: $report,
                        isSuccess: $isSuccess,
                        isAdminCopy: true,
                        wasBlocked: $wasBlocked
                    )
                );
            }

            Log::info('Simple unblock notifications sent successfully', [
                'report_id' => $report->id,
            ]);

        } catch (Throwable $e) {
            Log::error('Failed to send simple unblock notification', [
                'report_id' => $this->reportId,
                'email' => $this->email,
                'error' => $e->getMessage(),
            ]);

            // We rethrow the exception to let the queue handle retries/failures.
            throw $e;
        }
    }

    /**
     * Send notification to admin only (for alerts like suspicious activity)
     */
    private function sendAdminAlert(?string $reason = null): void
    {
        $adminEmail = config('unblock.admin_email');
        if (! $adminEmail) {
            Log::warning('No admin email configured for simple unblock admin alert');

            return;
        }

        Log::info('Sending simple unblock admin alert', [
            'email' => $this->email,
            'domain' => $this->domain,
            'reason' => $reason ?? $this->reason,
        ]);

        Mail::to($adminEmail)->send(
            new SimpleUnblockNotificationMail(
                email: $this->email,
                domain: $this->domain,
                report: null,
                isSuccess: false,
                isAdminCopy: true,
                reason: $reason ?? $this->reason,
                hostFqdn: $this->hostFqdn,
                analysisData: $this->analysisData
            )
        );
    }
}
