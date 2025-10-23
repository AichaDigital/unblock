<?php

declare(strict_types=1);

namespace App\Events\SimpleUnblock;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when OTP verification fails
 *
 * Triggered in SimpleUnblockForm::verifyOtp()
 * Used to track email reputation and create abuse incidents
 */
class SimpleUnblockOtpFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $email,
        public string $ip,
        public string $reason
    ) {}
}
