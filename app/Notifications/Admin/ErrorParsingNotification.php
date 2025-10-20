<?php

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ErrorParsingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $log,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Error de parseo de un log')
            ->view('emails.admin.error-parsing', [
                'log' => $this->log,
            ]);
    }

    public function toArray($notifiable): array
    {
        return [];
    }
}
