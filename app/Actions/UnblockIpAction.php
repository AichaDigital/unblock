<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PanelType;
use App\Models\{BfmWhitelistEntry, Host};
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

            // 1. Check if IP is in permanent deny list (csf.deny)
            $denyCheck = $this->firewallService->checkProblems($host, $keyName, 'csf_deny_check', $ip);
            if (! empty(trim($denyCheck))) {
                // IP is in permanent deny list - remove it
                $this->firewallService->checkProblems($host, $keyName, 'unblock_permanent', $ip);
                Log::info('Removed IP from permanent deny list', ['ip' => $ip, 'host' => $host->fqdn]);
            }

            // 2. Always remove any temporary block (AID-171). CSF v15 stores temp
            // bans in csf.tempban, not csf.tempip; `csf -tr` is idempotent.
            $this->firewallService->checkProblems($host, $keyName, 'unblock_temporary', $ip);
            Log::info('Removing any temporary block (csf -tr)', ['ip' => $ip, 'host' => $host->fqdn]);

            // 3. Add to CSF temporary whitelist (always, after removing denies)
            $this->firewallService->checkProblems($host, $keyName, 'whitelist_simple', $ip);

            // 3b. Verify the whitelist took effect (AID-171, quality/idempotence).
            $whitelistCheck = $this->firewallService->checkProblems($host, $keyName, 'csf_tempallow_check', $ip);
            $whitelistVerified = str_contains($whitelistCheck, $ip);
            if (! $whitelistVerified) {
                Log::error('CSF whitelist not verified after unblock', ['ip' => $ip, 'host' => $host->fqdn]);
            }

            // 4. For DirectAdmin servers, also handle BFM
            if ($host->panel === PanelType::DIRECTADMIN) {
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
                    BfmWhitelistEntry::create([
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
                'whitelist_verified' => $whitelistVerified,
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
