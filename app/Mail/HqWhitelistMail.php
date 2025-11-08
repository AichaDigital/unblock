<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\{Host, User};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

class HqWhitelistMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $ip,
        public readonly int $ttlSeconds,
        public readonly Host $hqHost,
        public readonly string $modsecLogs,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.hq_whitelist.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hq-whitelist',
            with: [
                'ip' => $this->ip,
                'userName' => $this->user->name,
                'ttlSeconds' => $this->ttlSeconds,
                'ttlHours' => round($this->ttlSeconds / 3600, 2),
                'companyName' => config('company.name'),
                'hqHostFqdn' => $this->hqHost->fqdn,
                'hqHostPanel' => $this->hqHost->panel ?? 'N/A',
                'modsecLogs' => $this->modsecLogs,
                'timestamp' => now()->format('Y-m-d H:i:s T'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
