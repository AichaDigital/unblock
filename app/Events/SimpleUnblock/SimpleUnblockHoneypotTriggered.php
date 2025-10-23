<?php

declare(strict_types=1);

namespace App\Events\SimpleUnblock;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when honeypot is triggered
 *
 * Triggered when Spatie Honeypot detects a bot
 * Used to create abuse incidents
 */
class SimpleUnblockHoneypotTriggered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $ip,
        public ?string $email = null,
        public ?string $domain = null
    ) {}
}
