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
            $ttl = config('unblock.simple_mode.whitelist_ttl', 3600);

            // 1. Standard CSF unblock (remove from deny lists)
            $this->firewallService->checkProblems($host, $keyName, 'unblock', $ip);

            // 2. Add to CSF temporary whitelist
            $this->firewallService->checkProblems($host, $keyName, 'whitelist_simple', $ip);

            // 3. For DirectAdmin servers, also handle BFM
            if ($host->panel === 'directadmin' || $host->panel === 'da') {
                try {
                    // a) Check if IP is in BFM blacklist
                    $bfmCheck = $this->firewallService->checkProblems($host, $keyName, 'da_bfm_check', $ip);

                    // b) If found in blacklist, remove it
                    if (! empty(trim($bfmCheck))) {
                        $this->firewallService->checkProblems($host, $keyName, 'da_bfm_remove', $ip);
                    }

                    // c) Add to BFM whitelist (always, even if not in blacklist)
                    $this->firewallService->checkProblems($host, $keyName, 'da_bfm_whitelist_add', $ip);

                    // d) Register in database for scheduled cleanup
                    \App\Models\BfmWhitelistEntry::create([
                        'host_id' => $host->id,
                        'ip_address' => $ip,
                        'added_at' => now(),
                        'expires_at' => now()->addSeconds($ttl),
                        'notes' => 'Auto-added by UnblockIpAction',
                    ]);

                } catch (Throwable $bfmException) {
                    // Log BFM error but don't fail the whole operation
                    Log::warning('Failed to process DirectAdmin BFM', [
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
