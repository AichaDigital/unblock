<?php

declare(strict_types=1);

namespace App\Actions\SimpleUnblock;

use App\Exceptions\ConnectionFailedException;
use App\Models\Host;
use App\Services\SshConnectionManager;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Check Domain In Server Logs Action
 *
 * Searches for domain in server logs (Apache/Nginx/Exim) using SSH commands.
 * This helps verify that the IP was actually accessing the domain.
 */
class CheckDomainInServerLogsAction
{
    use AsAction;

    public function __construct(
        private readonly SshConnectionManager $sshManager,
        private readonly BuildDomainSearchCommandsAction $buildCommands
    ) {}

    /**
     * Check if domain exists in server logs for given IP
     */
    public function handle(string $ip, string $domain, Host $host): DomainLogsSearchResult
    {
        Log::debug('Searching for domain in server logs', [
            'ip' => $ip,
            'domain' => $domain,
            'host_fqdn' => $host->fqdn,
            'panel' => $host->panel->value,
        ]);

        $session = $this->sshManager->createSession($host);

        try {
            // Build search commands
            $commands = $this->buildCommands->handle($ip, $domain, $host->panel->value);
            $searchedPaths = $this->extractSearchPaths($commands);

            // Combine commands with OR operator
            $combinedCommand = implode(' || ', $commands);

            // Execute search
            $result = $session->execute($combinedCommand);
            $found = ! empty(trim($result));

            Log::info('Domain search in logs completed', [
                'ip' => $ip,
                'domain' => $domain,
                'host_fqdn' => $host->fqdn,
                'found' => $found,
                'result_preview' => substr($result, 0, 200),
                'searched_paths' => $searchedPaths,
            ]);

            if ($found) {
                return DomainLogsSearchResult::found(
                    matchingLogs: [$result],
                    searchedPaths: $searchedPaths
                );
            }

            return DomainLogsSearchResult::notFound($searchedPaths);

        } catch (ConnectionFailedException $e) {
            Log::warning('Could not check domain in logs (connection failed)', [
                'ip' => $ip,
                'domain' => $domain,
                'host_id' => $host->id,
                'error' => $e->getMessage(),
            ]);

            // Return not found (fail-safe)
            return DomainLogsSearchResult::notFound([]);

        } finally {
            $session->cleanup();
        }
    }

    /**
     * Extract log paths from commands for logging purposes
     */
    private function extractSearchPaths(array $commands): array
    {
        $paths = [];

        foreach ($commands as $command) {
            if (preg_match('/find\s+([^\s]+)/', $command, $matches)) {
                $paths[] = $matches[1];
            }
        }

        return $paths;
    }
}
