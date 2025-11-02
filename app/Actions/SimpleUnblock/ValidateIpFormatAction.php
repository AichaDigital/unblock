<?php

declare(strict_types=1);

namespace App\Actions\SimpleUnblock;

use App\Exceptions\InvalidIpException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Validate IP Format Action
 *
 * Validates that an IP address has correct format (IPv4 or IPv6).
 * This is an atomic action following Single Responsibility Principle.
 */
class ValidateIpFormatAction
{
    use AsAction;

    /**
     * Validate IP address format
     *
     * @throws InvalidIpException if IP format is invalid
     */
    public function handle(string $ip): void
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidIpException("Invalid IP address format: {$ip}");
        }
    }
}
