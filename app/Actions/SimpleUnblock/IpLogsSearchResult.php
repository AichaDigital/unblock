<?php

declare(strict_types=1);

namespace App\Actions\SimpleUnblock;

/**
 * IP Logs Search Result DTO
 *
 * Represents the result of searching for an IP in server logs.
 */
readonly class IpLogsSearchResult
{
    /**
     * @param  array<string, string>  $logEntries
     */
    public function __construct(
        public string $ip,
        public bool $foundInLogs,
        public array $logEntries = []
    ) {}
}
