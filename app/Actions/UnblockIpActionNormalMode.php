<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PanelType;
use App\Models\Host;
use App\Services\Firewall\FirewallAnalysisResult;
use App\Services\{FirewallService, SshConnectionManager};
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Action to unblock an IP from the firewall - NORMAL MODE (authenticated users)
 *
 * This action handles:
 * - SSH connection setup with cleanup
 * - IP unblocking command execution
 * - Temporary whitelisting
 * - DirectAdmin BFM handling
 *
 * Differences from Simple Mode:
 * - Uses normal mode TTL (longer)
 * - Integrates with FirewallAnalysisResult
 * - Different email notifications
 */
class UnblockIpActionNormalMode
{
    use AsAction;

    public function __construct(
        protected FirewallService $firewallService,
        protected SshConnectionManager $sshManager
    ) {}

    /**
     * Handle IP unblocking process for Normal Mode
     *
     * @param  string  $ip  IP address to unblock
     * @param  int  $hostId  Host ID where to unblock
     * @param  FirewallAnalysisResult  $analysis  Analysis result
     * @return array{
     *     success: bool,
     *     message: string,
     *     error?: string
     * }
     */
    public function handle(string $ip, int $hostId, FirewallAnalysisResult $analysis): array
    {
        $keyPath = null;

        try {
            $host = Host::findOrFail($hostId);
            $ttl = config('unblock.whitelist_ttl', 86400); // Normal mode: 24h default

            // Generate temporary SSH key
            $keyPath = $this->sshManager->generateSshKey($host->hash);

            Log::info('Starting unblock process (Normal Mode)', [
                'ip' => $ip,
                'host' => $host->fqdn,
                'ttl' => $ttl,
            ]);

            // 1. Standard CSF unblock (remove from deny lists)
            Log::info('Executing CSF unblock command', ['ip' => $ip, 'host' => $host->fqdn]);
            $this->firewallService->checkProblems($host, $keyPath, 'unblock', $ip);

            // 2. Add to CSF temporary whitelist (normal mode TTL)
            Log::info('Adding IP to CSF temporary whitelist', ['ip' => $ip, 'host' => $host->fqdn, 'ttl' => $ttl]);
            $this->firewallService->checkProblems($host, $keyPath, 'whitelist', $ip);

            // 3. For DirectAdmin servers, also handle BFM
            if ($host->panel === PanelType::DIRECTADMIN) {
                try {
                    Log::info('DirectAdmin detected, processing BFM operations', ['ip' => $ip, 'host' => $host->fqdn]);

                    // a) Check if IP is in BFM blacklist
                    Log::debug('Checking if IP is in BFM blacklist', ['ip' => $ip]);
                    $bfmCheck = $this->firewallService->checkProblems($host, $keyPath, 'da_bfm_check', $ip);

                    // b) If found in blacklist, remove it
                    if (! empty(trim($bfmCheck))) {
                        Log::info('IP found in BFM blacklist, removing', ['ip' => $ip]);
                        $this->firewallService->checkProblems($host, $keyPath, 'da_bfm_remove', $ip);
                    } else {
                        Log::debug('IP not in BFM blacklist', ['ip' => $ip]);
                    }

                    // c) Add to BFM whitelist (always, even if not in blacklist)
                    Log::info('Adding IP to BFM whitelist', ['ip' => $ip]);
                    $this->firewallService->checkProblems($host, $keyPath, 'da_bfm_whitelist_add', $ip);

                    // d) Register in database for scheduled cleanup
                    Log::debug('Registering BFM whitelist entry in database', ['ip' => $ip, 'ttl' => $ttl]);
                    \App\Models\BfmWhitelistEntry::create([
                        'host_id' => $host->id,
                        'ip_address' => $ip,
                        'added_at' => now(),
                        'expires_at' => now()->addSeconds($ttl),
                        'notes' => 'Auto-added by UnblockIpActionNormalMode',
                    ]);
                    Log::info('BFM operations completed successfully', ['ip' => $ip]);

                } catch (Throwable $bfmException) {
                    // Log BFM error but don't fail the whole operation
                    Log::warning('Failed to process DirectAdmin BFM (Normal Mode)', [
                        'ip' => $ip,
                        'host' => $host->fqdn,
                        'error' => $bfmException->getMessage(),
                    ]);
                }
            }

            Log::info('Unblock process completed successfully (Normal Mode)', [
                'ip' => $ip,
                'host' => $host->fqdn,
            ]);

            return [
                'success' => true,
                'message' => __('messages.firewall.ip_unblocked'),
            ];

        } catch (Throwable $e) {
            Log::error('Unblock process failed (Normal Mode)', [
                'ip' => $ip,
                'host_id' => $hostId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => __('messages.firewall.unblock_failed'),
                'error' => $e->getMessage(),
            ];

        } finally {
            // Always cleanup SSH key
            if ($keyPath !== null) {
                try {
                    $this->sshManager->removeSshKey($keyPath);
                } catch (Throwable $cleanupError) {
                    Log::warning('Failed to cleanup SSH key (Normal Mode)', [
                        'key_path' => $keyPath,
                        'error' => $cleanupError->getMessage(),
                    ]);
                }
            }
        }
    }
}
