<?php

declare(strict_types=1);

namespace App\Actions\SimpleUnblock;

/**
 * Unblock Decision DTO
 *
 * Immutable data transfer object representing the decision
 * of whether to unblock an IP or not.
 */
readonly class UnblockDecision
{
    public function __construct(
        public bool $shouldUnblock,
        public string $reason
    ) {}

    /**
     * Create a decision to unblock
     */
    public static function unblock(string $reason): self
    {
        return new self(
            shouldUnblock: true,
            reason: $reason
        );
    }

    /**
     * Create a decision to gather data without unblocking (e.g., logs found but no block)
     */
    public static function gatherData(string $reason): self
    {
        return new self(
            shouldUnblock: false,
            reason: $reason
        );
    }

    /**
     * Create a decision to not unblock (no match)
     */
    public static function noMatch(string $reason): self
    {
        return new self(
            shouldUnblock: false,
            reason: $reason
        );
    }

    /**
     * Create a decision to abort (e.g., invalid pre-condition)
     */
    public static function abort(string $reason): self
    {
        return new self(
            shouldUnblock: false,
            reason: $reason
        );
    }
}
