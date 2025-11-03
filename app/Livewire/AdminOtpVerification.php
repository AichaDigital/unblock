<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\{Auth, Log, RateLimiter};
use Illuminate\View\View;
use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

/**
 * Admin OTP Verification Component
 *
 * Handles OTP code verification for admin panel access
 */
#[Layout('layouts.guest')]
#[Title('Admin Verification')]
class AdminOtpVerification extends Component
{
    public string $code = '';

    public bool $processing = false;

    public ?string $message = null;

    public ?string $messageType = null;

    public bool $canResend = false;

    /**
     * Component initialization
     */
    public function mount(): void
    {
        // CRITICAL: Verify user is actually authenticated
        if (! Auth::check()) {
            session()->flush();
            $this->redirect(route('filament.admin.auth.login'));

            return;
        }

        // Redirect if already verified
        if ($this->isOtpVerified()) {
            $this->redirect(route('filament.admin.pages.dashboard'));

            return;
        }

        // Redirect if no pending OTP
        if (! session()->has('admin_otp_pending_user_id')) {
            session()->flush();
            Auth::logout();
            $this->redirect(route('filament.admin.auth.login'));

            return;
        }

        // Check if OTP was just sent
        if (session()->has('otp_sent')) {
            $this->message = __('admin_otp.otp_sent');
            $this->messageType = 'success';
            session()->forget('otp_sent');
        }

        // Enable resend after 60 seconds
        $sentAt = session()->get('admin_otp_sent_at', 0);
        if (now()->timestamp - $sentAt > 60) {
            $this->canResend = true;
        }
    }

    /**
     * Check if OTP is already verified
     */
    private function isOtpVerified(): bool
    {
        return session()->get('admin_otp_verified') === true
            && session()->get('admin_otp_user_id') === Auth::id();
    }

    /**
     * Verify OTP code
     */
    public function verify(): void
    {
        $this->validate([
            'code' => 'required|string|size:6',
        ]);

        $this->processing = true;
        $this->message = null;

        // Rate limiting
        $key = 'admin-otp-verify:'.session()->getId();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->message = __('admin_otp.too_many_attempts', ['seconds' => $seconds]);
            $this->messageType = 'error';
            $this->processing = false;

            return;
        }

        try {
            $userId = session()->get('admin_otp_pending_user_id');
            $user = User::find($userId);

            if (! $user) {
                throw new \Exception('User not found');
            }

            // Verify OTP using Spatie package
            $result = $user->attemptLoginUsingOneTimePassword($this->code);

            if (! $result->isOk()) {
                // Increment rate limiter
                RateLimiter::hit($key, 300); // 5 minutes decay

                $this->message = $result->validationMessage();
                $this->messageType = 'error';

                Log::warning('Admin OTP verification failed', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => request()->ip(),
                    'reason' => $result->validationMessage(),
                ]);

                $this->processing = false;

                return;
            }

            // OTP verified successfully!
            session()->put('admin_otp_verified', true);
            session()->put('admin_otp_user_id', $user->id);
            session()->put('admin_otp_verified_at', now()->timestamp);
            session()->forget('admin_otp_pending_user_id');
            session()->forget('admin_otp_sent_at');

            // Clear rate limiter
            RateLimiter::clear($key);

            Log::info('Admin OTP verified successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => request()->ip(),
            ]);

            $this->message = __('admin_otp.verification_success');
            $this->messageType = 'success';

            // Redirect to admin dashboard (force full page reload to reset Alpine/Livewire state)
            $this->redirect(route('filament.admin.pages.dashboard'), navigate: false);

        } catch (\Exception $e) {
            $this->message = __('admin_otp.verification_error');
            $this->messageType = 'error';

            Log::error('Admin OTP verification error', [
                'error' => $e->getMessage(),
                'ip' => request()->ip(),
            ]);
        } finally {
            $this->processing = false;
        }
    }

    /**
     * Resend OTP code
     */
    public function resend(): void
    {
        // Rate limiting for resend
        $key = 'admin-otp-resend:'.session()->getId();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            $this->message = __('admin_otp.resend_too_many', ['seconds' => $seconds]);
            $this->messageType = 'error';

            return;
        }

        try {
            $userId = session()->get('admin_otp_pending_user_id');
            $user = User::find($userId);

            if (! $user) {
                throw new \Exception('User not found');
            }

            $user->sendOneTimePassword();

            RateLimiter::hit($key, 120); // 2 minutes decay

            session()->put('admin_otp_sent_at', now()->timestamp);

            $this->message = __('admin_otp.otp_resent');
            $this->messageType = 'success';
            $this->canResend = false;

            // Enable resend again after 60 seconds
            $this->js('setTimeout(() => $wire.canResend = true, 60000)');

            Log::info('Admin OTP resent', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => request()->ip(),
            ]);

        } catch (\Exception $e) {
            $this->message = __('admin_otp.resend_error');
            $this->messageType = 'error';

            Log::error('Admin OTP resend error', [
                'error' => $e->getMessage(),
                'ip' => request()->ip(),
            ]);
        }
    }

    /**
     * Cancel and logout
     */
    public function cancel(): void
    {
        session()->flush();
        Auth::logout();

        $this->redirect(route('filament.admin.auth.login'));
    }

    /**
     * Render the component
     */
    public function render(): View
    {
        return view('livewire.admin-otp-verification');
    }
}
