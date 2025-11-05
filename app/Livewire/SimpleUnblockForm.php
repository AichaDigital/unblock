<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\SimpleUnblockAction;
use App\Events\SimpleUnblock\{SimpleUnblockIpMismatch, SimpleUnblockOtpFailed, SimpleUnblockOtpSent, SimpleUnblockOtpVerified};
use App\Models\{Domain, User};
use Closure;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\{Layout, Title};
use Livewire\Component;
use Log;
use Str;

/**
 * Simple Unblock Form Component (v1.2.0 - OTP Verification)
 *
 * Two-step anonymous IP unblock form:
 * 1. Request with email → Send OTP
 * 2. Verify OTP → Process unblock
 */
#[Layout('layouts.guest')]
#[Title('Simple IP Unblock')]
class SimpleUnblockForm extends Component
{
    public string $ip = '';

    public string $domain = '';

    public string $email = '';

    public string $oneTimePassword = '';

    public int $step = 1; // 1 = Request OTP, 2 = Verify OTP, 3 = Process (from OTP login)

    public bool $isOtpVerified = false; // Flag to indicate OTP is already verified

    public bool $processing = false;

    public ?string $message = null;

    public ?string $messageType = null;

    public bool $cooldownActive = false;

    public int $cooldownSeconds = 0;

    private ?User $otpUser = null;

    /**
     * Component initialization
     */
    public function mount(): void
    {
        // Auto-detect user's IP address
        $this->ip = $this->detectUserIp();

        // Check if user is coming from OTP login (simple mode)
        // Look for the email in session OR check if user is authenticated as simple mode user
        if (session()->has('otp_request_email') || $this->isSimpleModeUser()) {
            // User is authenticated via OTP, go directly to processing step
            $this->step = 3;
            $this->isOtpVerified = true;
            $this->email = session()->get('otp_request_email', Auth::user()->email ?? '');
            $this->message = __('simple_unblock.otp_verified_ready');
            $this->messageType = 'success';
        }

        // Check if there's an active cooldown
        $this->checkCooldown();
    }

    /**
     * Check if current user is a simple mode user (temporary user created for simple unblock)
     */
    private function isSimpleModeUser(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        $user = Auth::user();

        return $user->first_name === 'Simple' &&
               $user->last_name === 'Unblock' &&
               ! $user->is_admin;
    }

    /**
     * Custom validation rule for domain against local database
     */
    protected function validateDomainRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            // Normalize domain (lowercase, remove www prefix)
            $normalized = strtolower(trim($value));
            $normalized = preg_replace('/^www\./i', '', $normalized);

            // Search domain in local database with eager loading
            $domain = Domain::with('account')
                ->where('domain_name', $normalized)
                ->first();

            if (! $domain) {
                $fail(__('simple_unblock.domain_not_found'));

                return;
            }

            // Verify account is not suspended
            if ($domain->account->suspended_at) {
                $fail(__('simple_unblock.account_suspended'));

                return;
            }

            // Verify account is not deleted
            if ($domain->account->deleted_at) {
                $fail(__('simple_unblock.account_deleted'));

                return;
            }
        };
    }

    /**
     * Step 1: Send OTP to email
     */
    public function sendOtp(): void
    {
        $this->validate([
            'ip' => 'required|ip',
            'domain' => ['required', 'string', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i', $this->validateDomainRule()],
            'email' => 'required|email',
        ]);

        $this->processing = true;
        $this->message = null;

        try {
            // Create or get temporary user for OTP
            $this->otpUser = User::firstOrCreate(
                ['email' => $this->email],
                [
                    'first_name' => 'Simple',
                    'last_name' => 'Unblock',
                    'password' => bcrypt(Str::random(32)),
                    'is_admin' => false,
                ]
            );

            // Bind IP to session for verification
            $ip = $this->detectUserIp();
            session()->put('simple_unblock_otp_ip', $ip);
            session()->put('simple_unblock_otp_data', [
                'ip' => $this->ip,
                'domain' => $this->domain,
                'email' => $this->email,
            ]);

            // Send OTP
            $this->otpUser->sendOneTimePassword();

            // Dispatch event for reputation tracking (v1.3.0)
            SimpleUnblockOtpSent::dispatch($this->email, $ip);

            // Move to step 2
            $this->step = 2;
            $this->message = __('simple_unblock.otp_sent');
            $this->messageType = 'success';

        } catch (Exception $e) {
            $this->message = __('simple_unblock.error_message');
            $this->messageType = 'error';

            Log::error('Simple unblock OTP send error', [
                'ip' => $this->ip,
                'domain' => $this->domain,
                'email' => $this->email,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->processing = false;
        }
    }

    /**
     * Step 2: Verify OTP and process unblock
     */
    public function verifyOtp(): void
    {
        $this->validate([
            'oneTimePassword' => 'required|string|size:6',
        ]);

        $this->processing = true;
        $this->message = null;

        try {
            // Get stored data
            $storedData = session()->get('simple_unblock_otp_data');
            $storedIp = session()->get('simple_unblock_otp_ip');
            $currentIp = $this->detectUserIp();

            // Verify IP match
            if ($storedIp !== $currentIp) {
                // Dispatch IP mismatch event (v1.3.0)
                SimpleUnblockIpMismatch::dispatch($storedIp, $currentIp, $storedData['email']);

                throw new Exception('IP mismatch during OTP verification');
            }

            // Get user
            $user = User::where('email', $storedData['email'])->first();
            if (! $user) {
                throw new Exception('User not found for OTP verification');
            }

            // Verify OTP
            $result = $user->attemptLoginUsingOneTimePassword($this->oneTimePassword);

            if (! $result->isOk()) {
                // Dispatch OTP failed event (v1.3.0)
                SimpleUnblockOtpFailed::dispatch(
                    $storedData['email'],
                    $currentIp,
                    $result->validationMessage()
                );

                $this->message = $result->validationMessage();
                $this->messageType = 'error';

                return;
            }

            // Dispatch OTP verified event (v1.3.0)
            SimpleUnblockOtpVerified::dispatch($storedData['email'], $currentIp);

            // OTP verified! Now process the unblock request
            SimpleUnblockAction::run(
                ip: $storedData['ip'],
                domain: $storedData['domain'],
                email: $storedData['email']
            );

            $this->message = __('simple_unblock.processing_message');
            $this->messageType = 'success';

            // Clear session and reset form
            session()->forget(['simple_unblock_otp_ip', 'simple_unblock_otp_data']);
            $this->reset(['domain', 'email', 'oneTimePassword', 'step']);
            $this->ip = $this->detectUserIp();
        } catch (Exception $e) {
            $this->message = __('simple_unblock.error_message');
            $this->messageType = 'error';

            Log::error('Simple unblock OTP verification error', [
                'email' => $this->email,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->processing = false;
        }
    }

    /**
     * Go back to step 1
     */
    public function backToStep1(): void
    {
        $this->step = 1;
        $this->oneTimePassword = '';
        $this->message = null;
    }

    /**
     * Detect user's IP address (v1.2.0 - Fixed IP Spoofing)
     *
     * Uses request()->ip() which respects TrustProxies configuration.
     * This prevents IP header spoofing attacks.
     */
    private function detectUserIp(): string
    {
        return (string) request()->ip();
    }

    /**
     * Process unblock request directly (when OTP is already verified)
     */
    public function processUnblock(): void
    {
        // Check cooldown before processing
        if ($this->cooldownActive) {
            $this->message = __('simple_unblock.cooldown_active', ['seconds' => $this->cooldownSeconds]);
            $this->messageType = 'warning';

            return;
        }

        $this->validate([
            'ip' => 'required|ip',
            'domain' => ['required', 'string', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i', $this->validateDomainRule()],
        ]);

        $this->processing = true;

        try {
            $action = new SimpleUnblockAction;
            $action->handle(
                ip: $this->ip,
                domain: $this->domain,
                email: $this->email
            );

            // Set cooldown (60 seconds for simple mode)
            $cooldownDuration = config('unblock.simple_mode.cooldown_seconds', 60);
            $this->setCooldown($cooldownDuration);

            // Success message with email notification info
            $this->message = __('simple_unblock.request_submitted');
            $this->messageType = 'success';

            // Reset form fields but keep IP
            $savedIp = $this->ip;
            $this->reset(['domain']);
            $this->ip = $savedIp;

            // Log activity
            Log::info('Simple unblock request submitted', [
                'ip' => $this->ip,
                'domain' => $this->domain,
                'email_hash' => hash('sha256', $this->email),
            ]);

        } catch (Exception $e) {
            Log::error('Simple unblock processing failed', [
                'ip' => $this->ip,
                'domain' => $this->domain,
                'email' => $this->email,
                'error' => $e->getMessage(),
            ]);

            $this->message = __('simple_unblock.error_message');
            $this->messageType = 'error';
        } finally {
            $this->processing = false;
        }
    }

    /**
     * Set cooldown to prevent spam
     */
    private function setCooldown(int $seconds): void
    {
        $expiresAt = now()->addSeconds($seconds);
        session()->put('simple_unblock_cooldown', $expiresAt->timestamp);

        $this->cooldownActive = true;
        $this->cooldownSeconds = $seconds;
    }

    /**
     * Check if cooldown is active
     */
    public function checkCooldown(): void
    {
        $cooldownTimestamp = session()->get('simple_unblock_cooldown');

        if (! $cooldownTimestamp) {
            $this->cooldownActive = false;
            $this->cooldownSeconds = 0;

            return;
        }

        $now = now()->timestamp;

        if ($cooldownTimestamp > $now) {
            $this->cooldownActive = true;
            $this->cooldownSeconds = $cooldownTimestamp - $now;
        } else {
            // Cooldown expired, clear session
            session()->forget('simple_unblock_cooldown');
            $this->cooldownActive = false;
            $this->cooldownSeconds = 0;
        }
    }

    /**
     * Render the component
     */
    public function render(): View
    {
        return view('livewire.simple-unblock-form');
    }
}
