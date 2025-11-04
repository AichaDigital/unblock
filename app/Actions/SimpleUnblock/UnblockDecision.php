<?php

declare(strict_types=1);

namespace App\Actions\SimpleUnblock;

/**
 * Unblock Decision DTO
 *
 * Immutable data transfer object representing the decision
 * of whether to unblock an IP or not, and what notifications to send.
 */
readonly class UnblockDecision
{
    public function __construct(
        public bool $shouldUnblock,
        public string $reason,
        public bool $notifyUser,
        public bool $notifyAdmin
    ) {}

    /**
     * Create a decision to unblock and notify both user and admin
     */
    public static function unblock(string $reason): self
    {
        return new self(
            shouldUnblock: true,
            reason: $reason,
            notifyUser: true,
            notifyAdmin: true
        );
    }

    /**
     * Create a decision to not unblock (no match)
     * User IS notified because their domain is valid - they should know the result
     */
    public static function noMatch(string $reason): self
    {
        return new self(
            shouldUnblock: false,
            reason: $reason,
            notifyUser: true,  // Changed from false - user deserves feedback
            notifyAdmin: true
        );
    }

    /**
     * Create a decision to abort (suspicious activity)
     */
    public static function abort(string $reason): self
    {
        return new self(
            shouldUnblock: false,
            reason: $reason,
            notifyUser: false,
            notifyAdmin: true
        );
    }
}
