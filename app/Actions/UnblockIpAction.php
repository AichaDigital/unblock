<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Host;
use App\Services\FirewallService;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Action to unblock an IP from the firewall
 *
 * This action handles:
 * - SSH connection setup
 * - IP unblocking command execution
 * - Cleanup
 */
class UnblockIpAction
{
    use AsAction;

    public function __construct(
        protected FirewallService $firewallService
    ) {}

    /**
     * Handle IP unblocking process
     *
     * @param  string  $ip  IP address to unblock
     * @param  int  $hostId  Host ID where to unblock
     * @param  string  $keyName  SSH key name to use
     * @return array{
     *     success: bool,
     *     message: string,
     *     error?: string
     * }
     */
    public function handle(string $ip, int $hostId, string $keyName): array
    {
        try {
            $host = Host::findOrFail($hostId);

            // Standard CSF unblock
            $this->firewallService->checkProblems($host, $keyName, 'unblock', $ip);

            // For DirectAdmin servers, also check and remove from BFM blacklist
            if ($host->panel === 'directadmin' || $host->panel === 'da') {
                try {
                    // Check if IP is in BFM blacklist
                    $bfmCheck = $this->firewallService->checkProblems($host, $keyName, 'da_bfm_check', $ip);

                    // If IP found in BFM blacklist, remove it
                    if (! empty(trim($bfmCheck))) {
                        $this->firewallService->checkProblems($host, $keyName, 'da_bfm_remove', $ip);
                    }
                } catch (Throwable $bfmException) {
                    // Log BFM error but don't fail the whole operation
                    Log::warning('Failed to check/remove IP from DirectAdmin BFM blacklist', [
                        'ip' => $ip,
                        'host' => $host->fqdn,
                        'error' => $bfmException->getMessage(),
                    ]);
                }
            }

            return [
                'success' => true,
                'message' => __('messages.firewall.ip_unblocked'),
            ];

        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => __('messages.firewall.unblock_failed'),
                'error' => $e->getMessage(),
            ];
        }
    }
}
