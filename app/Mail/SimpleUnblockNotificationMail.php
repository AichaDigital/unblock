<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Simple Unblock Notification Mail
 *
 * Email notification for anonymous IP unblock requests.
 * Supports two modes:
 * - Success: IP was unblocked (sent to user + admin)
 * - Silent: No match or failure (sent to admin only)
 */
class SimpleUnblockNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly string $email,
        public readonly string $domain,
        public readonly ?Report $report,
        public readonly bool $isSuccess,
        public readonly bool $isAdminCopy,
        public readonly ?string $reason = null,
        public readonly ?string $hostFqdn = null,
        public readonly ?array $analysisData = null
    ) {
        // Set locale for email rendering (anonymous users use APP_LOCALE)
        app()->setLocale(config('app.locale'));
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->isSuccess
            ? __('simple_unblock.mail.success_subject', ['domain' => $this->domain])
            : __('simple_unblock.mail.admin_alert_subject', ['domain' => $this->domain]);

        if ($this->isAdminCopy && $this->isSuccess) {
            $subject = '[ADMIN] '.$subject;
        }

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $view = $this->isSuccess
            ? 'emails.simple-unblock-success'
            : 'emails.simple-unblock-admin-alert';

        return new Content(
            view: $view,
            with: [
                'email' => $this->email,
                'domain' => $this->domain,
                'report' => $this->report,
                'isAdminCopy' => $this->isAdminCopy,
                'reason' => $this->reason,
                'hostFqdn' => $this->hostFqdn,
                'analysisData' => $this->analysisData,
                'companyName' => config('company.name'),
                'supportEmail' => config('company.support.email'),
            ],
        );
    }
}
