<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\SimpleUnblockAction;
use App\Events\SimpleUnblock\SimpleUnblockIpMismatch;
use App\Events\SimpleUnblock\SimpleUnblockOtpFailed;
use App\Events\SimpleUnblock\SimpleUnblockOtpSent;
use App\Events\SimpleUnblock\SimpleUnblockOtpVerified;
use App\Models\User;
use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

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

    public int $step = 1; // 1 = Request OTP, 2 = Verify OTP

    public bool $processing = false;

    public ?string $message = null;

    public ?string $messageType = null;

    private ?User $otpUser = null;

    /**
     * Component initialization
     */
    public function mount(): void
    {
        // Auto-detect user's IP address
        $this->ip = $this->detectUserIp();
    }

    /**
     * Step 1: Send OTP to email
     */
    public function sendOtp(): void
    {
        $this->validate([
            'ip' => 'required|ip',
            'domain' => ['required', 'string', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
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
                    'password' => bcrypt(\Str::random(32)),
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

        } catch (\Exception $e) {
            $this->message = __('simple_unblock.error_message');
            $this->messageType = 'error';

            \Log::error('Simple unblock OTP send error', [
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

                throw new \Exception('IP mismatch during OTP verification');
            }

            // Get user
            $user = User::where('email', $storedData['email'])->first();
            if (! $user) {
                throw new \Exception('User not found for OTP verification');
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
        } catch (\Exception $e) {
            $this->message = __('simple_unblock.error_message');
            $this->messageType = 'error';

            \Log::error('Simple unblock OTP verification error', [
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
     * Render the component
     */
    public function render(): \Illuminate\View\View
    {
        return view('livewire.simple-unblock-form');
    }
}
