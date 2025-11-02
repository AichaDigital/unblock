<?php

declare(strict_types=1);

namespace App\Actions\SimpleUnblock;

use App\Models\Domain;

/**
 * Domain Validation Result DTO
 *
 * Immutable data transfer object for domain validation results.
 */
readonly class DomainValidationResult
{
    public function __construct(
        public bool $exists,
        public ?Domain $domain = null,
        public ?string $reason = null
    ) {}

    /**
     * Create success result
     */
    public static function success(Domain $domain): self
    {
        return new self(
            exists: true,
            domain: $domain,
            reason: null
        );
    }

    /**
     * Create failure result
     */
    public static function failure(string $reason): self
    {
        return new self(
            exists: false,
            domain: null,
            reason: $reason
        );
    }
}
