<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\{Log, Mail};
use Throwable;

class AuthenticationException extends Exception
{
    protected string $ip;

    protected string $email;

    public function __construct(
        string $ip,
        string $email,
        string $message = 'Excessive failed authentication attempts from IP',
        int $code = 0,
        ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->ip = $ip;
        $this->email = $email;
    }

    public function report(): void
    {
        Log::channel('login_errors')->error("{$this->ip} {$this->email} Excessive failed authentication attempts from IP {$this->ip}");

        Mail::raw("Atención administradores,\n\nHemos detectado varios intentos fallidos de autenticación desde la IP: {$this->ip}, para el email: {$this->email}.", function ($message) {
            $message->to(config('unblock.admin_email'))
                ->subject('Alerta de seguridad: Excesivos intentos fallidos de autenticación');
        });
    }
}
