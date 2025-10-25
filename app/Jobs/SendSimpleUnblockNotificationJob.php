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
        public readonly bool $adminOnly = false,
        public readonly ?string $reason = null,
        public readonly ?string $hostFqdn = null,
        public readonly ?array $analysisData = null
    ) {}

    /**
     * Execute the job
     */
    public function handle(): void
    {
        try {
            if ($this->adminOnly) {
                // Silent mode: Only notify admin
                $this->sendAdminOnlyNotification();
            } else {
                // Full match: Notify user + admin
                $this->sendUserAndAdminNotification();
            }
        } catch (Throwable $e) {
            Log::error('Failed to send simple unblock notifications', [
                'report_id' => $this->reportId,
                'email' => $this->email,
                'domain' => $this->domain,
                'admin_only' => $this->adminOnly,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send notification to both user and admin (full match scenario)
     */
    private function sendUserAndAdminNotification(): void
    {
        if (! $this->reportId) {
            Log::warning('Cannot send user notification without report ID');

            return;
        }

        $report = Report::find($this->reportId);
        if (! $report) {
            Log::warning('Report not found for simple unblock notification', [
                'report_id' => $this->reportId,
            ]);

            return;
        }

        Log::info('Sending simple unblock notification to user and admin', [
            'report_id' => $report->id,
            'email' => $this->email,
            'domain' => $this->domain,
        ]);

        // 1. Send to user
        Mail::to($this->email)->send(
            new SimpleUnblockNotificationMail(
                email: $this->email,
                domain: $this->domain,
                report: $report,
                isSuccess: true,
                isAdminCopy: false
            )
        );

        // 2. Send to admin
        $adminEmail = config('unblock.admin_email');
        if ($adminEmail && $adminEmail !== $this->email) {
            Mail::to($adminEmail)->send(
                new SimpleUnblockNotificationMail(
                    email: $this->email,
                    domain: $this->domain,
                    report: $report,
                    isSuccess: true,
                    isAdminCopy: true
                )
            );
        }

        Log::info('Simple unblock notifications sent successfully', [
            'report_id' => $report->id,
            'user_email' => $this->email,
            'admin_email' => $adminEmail ?? 'none',
        ]);
    }

    /**
     * Send notification to admin only (no match / partial match / failure)
     */
    private function sendAdminOnlyNotification(): void
    {
        $adminEmail = config('unblock.admin_email');
        if (! $adminEmail) {
            Log::warning('No admin email configured for simple unblock silent notification');

            return;
        }

        Log::info('Sending simple unblock silent notification to admin only', [
            'email' => $this->email,
            'domain' => $this->domain,
            'reason' => $this->reason,
            'host_fqdn' => $this->hostFqdn,
        ]);

        Mail::to($adminEmail)->send(
            new SimpleUnblockNotificationMail(
                email: $this->email,
                domain: $this->domain,
                report: null,
                isSuccess: false,
                isAdminCopy: true,
                reason: $this->reason,
                hostFqdn: $this->hostFqdn,
                analysisData: $this->analysisData
            )
        );

        Log::info('Simple unblock silent notification sent to admin', [
            'admin_email' => $adminEmail,
            'reason' => $this->reason,
        ]);
    }
}
