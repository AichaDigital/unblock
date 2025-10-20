<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\{Host, User};
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

class UserSystemErrorMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $ip,
        public readonly Host $host,
        public readonly User $user
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Error Temporal del Sistema - Verificación de Firewall',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user.system-error',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
