<?php

declare(strict_types=1);

namespace App\Events\SimpleUnblock;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when IP mismatch is detected during OTP verification
 *
 * Triggered in SimpleUnblockForm::verifyOtp()
 * Used to create abuse incidents (possible OTP relay attack)
 */
class SimpleUnblockIpMismatch
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $originalIp,
        public string $verificationIp,
        public string $email
    ) {}
}
