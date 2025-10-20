<?php

namespace App\Livewire;

use App\Models\User;
use App\Traits\AuditLoginTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\{Layout, On};
use Livewire\Component;
use WireUi\Traits\WireUiActions;

#[Layout('layouts.guest')]
class OtpLogin extends Component
{
    use AuditLoginTrait, WireUiActions;

    // Email step
    public string $email = '';

    public bool $sendingOtp = false;

    public bool $otpSent = false;

    // OTP step
    public string $oneTimePassword = '';

    public bool $authenticating = false;

    public bool $canResend = false;

    // Internal state
    public ?User $user = null;

    public function mount()
    {
        // If user is already authenticated, redirect to dashboard
        if (Auth::check()) {
            $this->redirectRoute('dashboard');

            return;
        }

        // Check if there's a session expired message
        if (session()->has('message')) {
            $this->notification()->send([
                'icon' => 'warning',
                'title' => 'Sesión expirada',
                'description' => session('message'),
            ]);
        }
    }

    private function getClientIp(): string
    {
        if (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipArray = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);

            return trim($ipArray[0]);
        }

        // In testing, use default IP if REMOTE_ADDR doesn't exist
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function sendOtp(): void
    {
        $this->sendingOtp = true;
        $ip = $this->getClientIp();
        $this->email = trim($this->email);

        try {
            $this->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            $this->user = User::whereEmail($this->email)->first();
            $this->audit($ip, 'email', null);

            // Send OTP using Spatie (with integrated rate limiting)
            $this->user->sendOneTimePassword();

            // Bind OTP request to client IP for later verification
            session()->put('otp_request_ip', $ip);

            $this->notification()->send([
                'icon' => 'info',
                'title' => 'Código enviado',
                'description' => 'Se ha enviado un código de 6 dígitos a '.$this->email,
            ]);

            $this->otpSent = true;
            $this->canResend = false;

            // Allow resend after 60 seconds
            $this->js('setTimeout(() => $wire.enableResend(), 60000)');

        } catch (ValidationException $e) {
            $validationErrors = $e->validator->errors()->getMessages();

            $this->notification()->send([
                'icon' => 'error',
                'title' => 'Error de validación',
                'description' => $validationErrors['email'][0] ?? 'Email inválido',
            ]);

            $validationError = $this->email.' : '.($validationErrors['email'][0] ?? 'Email inválido');
            $this->audit($ip, 'email', $validationError, true);
        }

        $this->sendingOtp = false;
    }

    public function verifyOtp(): void
    {
        $this->authenticating = true;
        $ip = $this->getClientIp();

        try {
            $this->validate([
                'oneTimePassword' => 'required|string|size:6',
            ]);

            if (! $this->user) {
                throw ValidationException::withMessages([
                    'oneTimePassword' => 'Sesión expirada. Solicita un nuevo código.',
                ]);
            }

            // Enforce same-IP usage for the OTP
            $storedIp = session()->get('otp_request_ip');
            if (! empty($storedIp) && $storedIp !== $ip) {
                $this->audit($ip, 'otp_login', 'IP mismatch on OTP verification. Expected '.$storedIp.' got '.$ip, true);

                throw ValidationException::withMessages([
                    'oneTimePassword' => __('auth.ip_mismatch'),
                ]);
            }

            // Attempt to authenticate with OTP using Spatie
            $result = $this->user->attemptLoginUsingOneTimePassword($this->oneTimePassword);

            if ($result->isOk()) {
                // Successful login - Spatie automatically authenticates the user
                $this->audit($ip, 'otp_login', 'Successful OTP authentication');

                // Set initial session activity timestamp
                session()->put('last_activity', now()->timestamp);

                $this->notification()->send([
                    'icon' => 'success',
                    'title' => 'Acceso autorizado',
                    'description' => 'Bienvenido al sistema',
                ]);

                $this->redirectRoute('dashboard');

                return;
            } else {
                // Incorrect or expired OTP - get specific message from enum
                $errorMessage = $result->validationMessage();

                $this->notification()->send([
                    'icon' => 'error',
                    'title' => 'Código inválido',
                    'description' => $errorMessage,
                ]);

                $this->audit($ip, 'otp_login', 'Failed OTP attempt: '.$this->oneTimePassword, true);

                // Don't clear the field, let user try again or modify
                // Only clear if it's clearly expired/consumed
                if (str_contains(strtolower($errorMessage), 'expired') || str_contains(strtolower($errorMessage), 'consumed')) {
                    $this->oneTimePassword = '';
                }
            }

        } catch (ValidationException $e) {
            $validationErrors = $e->validator->errors()->getMessages();

            if (isset($validationErrors['oneTimePassword'])) {
                $this->notification()->send([
                    'icon' => 'error',
                    'title' => 'Error de validación',
                    'description' => $validationErrors['oneTimePassword'][0],
                ]);

                $this->audit($ip, 'otp_login', 'Failed OTP validation: '.$this->oneTimePassword, true);
            }

            // Only clear field for validation errors (wrong format)
            if (isset($validationErrors['oneTimePassword']) && str_contains($validationErrors['oneTimePassword'][0], 'size')) {
                // Don't clear for size errors, user might be typing
            } else {
                $this->oneTimePassword = '';
            }
        }

        $this->authenticating = false;
    }

    public function resendOtp(): void
    {
        if (! $this->canResend || ! $this->user) {
            return;
        }

        $this->user->sendOneTimePassword();

        $this->notification()->send([
            'icon' => 'info',
            'title' => 'Código reenviado',
            'description' => 'Se ha enviado un nuevo código a '.$this->email,
        ]);

        $this->canResend = false;
        $this->js('setTimeout(() => $wire.enableResend(), 60000)');
    }

    public function resetForm(): void
    {
        $this->otpSent = false;
        $this->email = '';
        $this->oneTimePassword = '';
        $this->user = null;
        $this->sendingOtp = false;
        $this->authenticating = false;
        $this->canResend = false;
    }

    #[On('enableResend')]
    public function enableResend(): void
    {
        $this->canResend = true;
    }

    public function render()
    {
        return view('livewire.otp-login');
    }
}
