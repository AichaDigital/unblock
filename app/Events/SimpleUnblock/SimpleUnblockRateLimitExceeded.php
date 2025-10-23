<?php

declare(strict_types=1);

namespace App\Events\SimpleUnblock;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when rate limit is exceeded
 *
 * Triggered in ThrottleSimpleUnblock middleware or SimpleUnblockAction
 * Used to create abuse incidents
 */
class SimpleUnblockRateLimitExceeded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $vector, // ip, email, domain, subnet, global
        public string $identifier, // the actual value (IP, email hash, domain, subnet)
        public int $attempts,
        public int $maxAttempts
    ) {}
}
