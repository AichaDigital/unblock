<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\{Host, User};
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

class AdminConnectionErrorMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $ip,
        public readonly Host $host,
        public readonly User $user,
        public readonly string $errorMessage,
        public readonly Exception $exception
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[CRÍTICO] Error de Conexión SSH - Sistema Firewall',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.connection-error',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
