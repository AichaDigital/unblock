<?php

declare(strict_types=1);

namespace App\Actions\SimpleUnblock;

/**
 * Domain Logs Search Result DTO
 *
 * Immutable data transfer object for domain search in server logs.
 */
readonly class DomainLogsSearchResult
{
    public function __construct(
        public bool $found,
        public array $matchingLogs = [],
        public array $searchedPaths = []
    ) {}

    /**
     * Create found result
     */
    public static function found(array $matchingLogs, array $searchedPaths): self
    {
        return new self(
            found: true,
            matchingLogs: $matchingLogs,
            searchedPaths: $searchedPaths
        );
    }

    /**
     * Create not found result
     */
    public static function notFound(array $searchedPaths): self
    {
        return new self(
            found: false,
            matchingLogs: [],
            searchedPaths: $searchedPaths
        );
    }
}
