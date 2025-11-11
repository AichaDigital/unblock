<?php

namespace App\Livewire;

use App\Models\User;
use App\Traits\{AuditLoginTrait, HasNotifications};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\{Layout, On};
use Livewire\Component;

#[Layout('layouts.guest')]
class OtpLogin extends Component
{
    use AuditLoginTrait, HasNotifications;

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

    /**
     * Check if simple mode is enabled
     */
    private function isSimpleMode(): bool
    {
        return config('unblock.simple_mode.enabled', false);
    }

    /**
     * Get dynamic title based on mode
     */
    public function getTitle(): string
    {
        return $this->isSimpleMode()
            ? __('auth.simple_mode_title')
            : __('Solo cuentas de cliente');
    }

    /**
     * Get dynamic email placeholder based on mode
     */
    public function getEmailPlaceholder(): string
    {
        return $this->isSimpleMode()
            ? __('auth.simple_mode_email_placeholder')
            : __('Cuenta de correo electrónico de usuario');
    }

    /**
     * Get dynamic email help text based on mode
     */
    public function getEmailHelpText(): string
    {
        return $this->isSimpleMode()
            ? __('auth.simple_mode_email_help')
            : __('Cuenta de usuario o usuario autorizado');
    }

    public function mount()
    {
        // If user is already authenticated, redirect to dashboard (only in normal mode)
        if (Auth::check() && ! $this->isSimpleMode()) {
            $this->redirectRoute('dashboard');

            return;
        }

        // Check if there's a session expired message
        if (session()->has('message')) {
            $this->warning(
                'Sesión expirada',
                session('message')
            );
            session()->forget('message');
        }

        // CRITICAL: Reset form state completely (avoid zombie state)
        $this->resetFormState();
    }

    /**
     * Reset form state completely to prevent zombie states
     */
    private function resetFormState(): void
    {
        $this->otpSent = false;
        $this->email = '';
        $this->oneTimePassword = '';
        $this->user = null;
        $this->sendingOtp = false;
        $this->authenticating = false;
        $this->canResend = false;
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
            // Adaptive validation based on mode
            $rules = ['email' => 'required|email'];
            if (! $this->isSimpleMode()) {
                $rules['email'] .= '|exists:users,email';
            }
            $this->validate($rules);

            // Get or create user based on mode
            if ($this->isSimpleMode()) {
                // Simple mode: Create temporary user if doesn't exist
                $this->user = User::firstOrCreate(
                    ['email' => $this->email],
                    [
                        'first_name' => 'Simple',
                        'last_name' => 'Unblock',
                        'password' => bcrypt(Str::random(32)),
                        'is_admin' => false,
                    ]
                );
            } else {
                // Normal mode: User must exist
                $this->user = User::whereEmail($this->email)->first();
            }

            $this->audit($ip, 'email', null);

            // Send OTP using Spatie (with integrated rate limiting)
            $this->user->sendOneTimePassword();

            // Bind OTP request to client IP for later verification
            session()->put('otp_request_ip', $ip);

            $this->info(
                'Código enviado',
                'Se ha enviado un código de 6 dígitos a '.$this->email
            );

            $this->otpSent = true;
            $this->canResend = false;

            // Allow resend after 60 seconds
            $this->js('setTimeout(() => $wire.enableResend(), 60000)');

        } catch (ValidationException $e) {
            $validationErrors = $e->validator->errors()->getMessages();

            $this->error(
                'Error de validación',
                $validationErrors['email'][0] ?? 'Email inválido'
            );

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
                // Successful OTP verification
                $this->audit($ip, 'otp_login', 'Successful OTP authentication');

                if ($this->isSimpleMode()) {
                    // Simple mode: Authenticate temporary user and redirect to simple unblock form
                    // In simple mode, even admins can use this for quick IP unblock
                    Auth::login($this->user);
                    session()->put('last_activity', now()->timestamp);
                    session()->put('otp_request_email', $this->email);

                    $this->success(
                        'Código verificado correctamente',
                        'Redirigiendo al formulario de desbloqueo...'
                    );

                    // Redirect to simple unblock form
                    $this->redirectRoute('simple.unblock');
                } else {
                    // Normal mode: Authenticate user and redirect to dashboard
                    Auth::login($this->user);
                    session()->put('last_activity', now()->timestamp);

                    $this->success(
                        'Acceso autorizado',
                        'Bienvenido al sistema'
                    );

                    $this->redirectRoute('dashboard');
                }

                return;
            } else {
                // Incorrect or expired OTP - get specific message from enum
                $errorMessage = $result->validationMessage();

                $this->error(
                    'Código inválido',
                    $errorMessage
                );

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
                $this->error(
                    'Error de validación',
                    $validationErrors['oneTimePassword'][0]
                );

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

        $this->info(
            'Código reenviado',
            'Se ha enviado un nuevo código a '.$this->email
        );

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
