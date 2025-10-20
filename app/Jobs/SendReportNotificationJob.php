<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\LogNotificationMail;
use App\Models\{Report, User};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\{Log, Mail};

/**
 * Job to send report notifications
 *
 * Handles sending email notifications when a firewall report is created
 */
class SendReportNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance
     */
    public function __construct(
        public readonly string $reportId,
        public readonly ?int $copyUserId = null
    ) {}

    /**
     * Execute the job
     */
    public function handle(): void
    {
        $report = Report::find($this->reportId);

        if (! $report) {
            Log::warning('Report not found for notification', [
                'report_id' => $this->reportId,
            ]);

            return;
        }

        try {
            $emailsSent = [];

            Log::info('Starting notification process', [
                'report_id' => $report->id,
                'copy_user_id' => $this->copyUserId,
                'requesting_user_email' => $report->user?->email,
            ]);

            // 1. Send email to the user who requested the check (ALWAYS)
            $this->sendUserNotification($report);
            $emailsSent[] = $report->user->email;

            // 2. Send copy to admin (ONLY if different from requesting user)
            $adminEmail = config('unblock.admin_email');
            $adminUser = User::where('is_admin', true)->first();

            // If we have a configured admin email
            if ($adminEmail && ! in_array($adminEmail, $emailsSent)) {
                // Use admin user if available, or create a temporary user object for the email
                $recipientUser = $adminUser ?: new User(['email' => $adminEmail, 'name' => 'Admin']);
                $this->sendAdminNotification($report, $recipientUser, $adminEmail);
                $emailsSent[] = $adminEmail;
            }
            // If we have an admin user but no configured email
            elseif ($adminUser && $adminUser->email && ! in_array($adminUser->email, $emailsSent)) {
                $this->sendAdminNotification($report, $adminUser);
                $emailsSent[] = $adminUser->email;
            }

            // 3. Send copy to additional user if specified (ONLY if different from previous recipients)
            if ($this->copyUserId) {
                $copyUser = User::find($this->copyUserId);
                if (! $copyUser) {
                    Log::warning('Copy user not found', ['copy_user_id' => $this->copyUserId]);
                } elseif (! $copyUser->email) {
                    Log::warning('Copy user has no email', ['copy_user_id' => $this->copyUserId]);
                } elseif (! in_array($copyUser->email, $emailsSent, true)) {
                    $this->sendCopyNotification($report, $copyUser);
                    $emailsSent[] = $copyUser->email;
                }
            }

        } catch (\Throwable $e) {
            Log::error('Failed to send report notifications', [
                'report_id' => $report->id,
                'user_id' => $report->user_id,
                'host_id' => $report->host_id,
                'ip' => $report->ip,
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger job retry
            throw $e;
        }
    }

    /**
     * Send notification to the user who requested the check
     */
    private function sendUserNotification(Report $report): void
    {
        if (! $report->user || ! $report->user->email) {
            Log::warning('Cannot send user notification: user or email not found', [
                'report_id' => $report->id,
                'user_id' => $report->user_id,
            ]);

            return;
        }

        // Determinar si hubo un desbloqueo basándose en el análisis
        $wasBlocked = $this->determineIfWasBlocked($report);

        Mail::to($report->user->email)->send(
            new LogNotificationMail(
                user: $report->user,
                report: [
                    'logs' => $report->logs,
                    'analysis' => $report->analysis,
                    'host' => data_get($report, 'host.fqdn', 'Unknown'),
                ],
                ip: $report->ip,
                is_unblock: $wasBlocked,
                report_uuid: (string) $report->id
            )
        );
    }

    /**
     * Send notification copy to admin
     */
    private function sendAdminNotification(Report $report, User $adminUser, ?string $overrideEmail = null): void
    {
        // Use override email, admin email from config, or admin user email (in that order)
        $adminEmail = $overrideEmail ?: (config('unblock.admin_email') ?: $adminUser->email);

        // Determine if IP was blocked based on analysis
        $wasBlocked = $this->determineIfWasBlocked($report);

        Mail::to($adminEmail)->send(
            new LogNotificationMail(
                user: $adminUser,
                report: [
                    'logs' => $report->logs,
                    'analysis' => $report->analysis,
                    'host' => data_get($report, 'host.fqdn', 'Unknown'),
                    'requested_by' => data_get($report, 'user.email', 'Unknown user'),
                ],
                ip: $report->ip,
                is_unblock: $wasBlocked,
                report_uuid: (string) $report->id
            )
        );
    }

    /**
     * Determine if IP was blocked based on analysis results
     *
     * CRITICAL FIX: Only use analysis['was_blocked'] from corrected analyzers.
     * NO fallback to log patterns (that was causing the original bug).
     */
    private function determineIfWasBlocked(Report $report): bool
    {
        // Check if analysis indicates the IP was blocked
        $analysis = $report->analysis ?? [];

        // ONLY use 'was_blocked' flag from analysis - analyzers are now corrected
        // to only set this to true when IP is ACTUALLY blocked by CSF/BFM/ModSecurity
        if (isset($analysis['was_blocked'])) {
            return (bool) $analysis['was_blocked'];
        }

        // If no analysis available, assume no block (defensive approach)
        // Better to err on the side of "no block" than false "IP desbloqueada correctamente"
        return false;
    }

    /**
     * Send notification copy to an additional user
     */
    private function sendCopyNotification(Report $report, User $copyUser): void
    {
        // Determinar si hubo un desbloqueo basándose en el análisis
        $wasBlocked = $this->determineIfWasBlocked($report);

        Mail::to($copyUser->email)->send(
            new LogNotificationMail(
                user: $copyUser,
                report: [
                    'logs' => $report->logs,
                    'analysis' => $report->analysis,
                    'host' => data_get($report, 'host.fqdn', 'Unknown'),
                    'requested_by' => data_get($report, 'user.email', 'Unknown user'),
                ],
                ip: $report->ip,
                is_unblock: $wasBlocked,
                report_uuid: (string) $report->id
            )
        );
    }
}
