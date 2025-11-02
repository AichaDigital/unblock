<?php

declare(strict_types=1);

namespace App\Actions\SimpleUnblock;

use App\Models\Host;
use App\Services\FirewallService;
use Illuminate\Support\Facades\Log;

/**
 * Check IP In Server Logs Action
 *
 * Searches for IP (NOT domain) in server logs (Exim/Dovecot).
 * This helps verify that the IP was actually attempting authentication.
 */
class CheckIpInServerLogsAction
{
    public function __construct(
        private readonly FirewallService $firewallService
    ) {}

    /**
     * Check if IP exists in server logs
     *
     * @param  Host  $host  Host to search logs on
     * @param  string  $keyPath  SSH key path
     * @param  string  $ip  IP to search for
     * @param  string  $domain  Domain (IGNORED - for backward compatibility only)
     * @return IpLogsSearchResult Result object
     */
    public function handle(Host $host, string $keyPath, string $ip, string $domain): IpLogsSearchResult
    {
        Log::debug('Searching for IP in server logs (domain ignored)', [
            'ip' => $ip,
            'domain_ignored' => $domain,
            'host_fqdn' => $host->fqdn,
            'panel' => $host->panel->value,
        ]);

        $foundInLogs = false;
        $logResults = [];

        try {
            // Determine log commands based on panel type
            $commands = $this->getLogCommands($host->panel->value);

            foreach ($commands as $service => $command) {
                $result = $this->firewallService->checkProblems($host, $keyPath, $command, $ip);

                if (! empty(trim($result))) {
                    $foundInLogs = true;
                    $logResults[$service] = substr($result, 0, 500); // Limit result size
                }
            }

            Log::info('IP search in logs completed', [
                'ip' => $ip,
                'host_fqdn' => $host->fqdn,
                'found' => $foundInLogs,
                'services_found' => array_keys($logResults),
            ]);

            return new IpLogsSearchResult(
                ip: $ip,
                foundInLogs: $foundInLogs,
                logEntries: $logResults
            );

        } catch (\Throwable $e) {
            Log::warning('Could not check IP in logs', [
                'ip' => $ip,
                'host_id' => $host->id,
                'error' => $e->getMessage(),
            ]);

            // Return not found (fail-safe)
            return new IpLogsSearchResult(
                ip: $ip,
                foundInLogs: false,
                logEntries: []
            );
        }
    }

    /**
     * Get log command identifiers based on panel type
     *
     * @return array<string, string> Service => Command ID mapping
     */
    private function getLogCommands(string $panel): array
    {
        return match (strtolower($panel)) {
            'directadmin', 'da' => [
                'exim' => 'exim_directadmin',
                'dovecot' => 'dovecot_directadmin',
            ],
            default => [
                'exim' => 'exim_cpanel',
                'dovecot' => 'dovecot_cpanel',
            ],
        };
    }
}
