<?php

declare(strict_types=1);

namespace App\Events\SimpleUnblock;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when OTP is sent to user
 *
 * Triggered in SimpleUnblockForm::sendOtp()
 * Used to track email reputation
 */
class SimpleUnblockOtpSent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $email,
        public string $ip
    ) {}
}
