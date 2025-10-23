<?php

declare(strict_types=1);

namespace App\Events\SimpleUnblock;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a Simple Unblock request is processed
 *
 * Triggered after SimpleUnblockAction completes successfully
 * Used to track IP reputation
 */
class SimpleUnblockRequestProcessed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $ip,
        public string $domain,
        public string $email,
        public bool $success = true
    ) {}
}
