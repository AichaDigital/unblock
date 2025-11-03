<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

class LogNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly array $report,
        public readonly string $ip,
        public readonly bool $is_unblock,
        public readonly string $report_uuid,
    ) {
        // Set locale for email rendering
        app()->setLocale($user->locale ?? config('app.locale'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Blocked IP Report',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.log-notification',
            with: [
                'report' => $this->report,
                'ip' => $this->ip,
                'userName' => $this->user->name,
                'is_unblock' => $this->is_unblock,
                'report_uuid' => $this->report_uuid,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
