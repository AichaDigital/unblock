<?php

declare(strict_types=1);

namespace App\Events\SimpleUnblock;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when OTP is successfully verified
 *
 * Triggered in SimpleUnblockForm::verifyOtp()
 * Used to track email reputation (increment verified_requests)
 */
class SimpleUnblockOtpVerified
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $email,
        public string $ip
    ) {}
}
