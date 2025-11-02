<?php

declare(strict_types=1);

namespace App\Actions\Sync;

use App\Enums\PanelType;
use App\Models\{Account, Domain, Host};
use App\Services\SshConnectionManager;
use Exception;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Sync cPanel Accounts Action
 *
 * Synchronizes hosting accounts and domains from a cPanel server to the local database.
 * Uses whmapi1 to fetch account data and creates/updates Account and Domain models.
 *
 * Execution modes:
 * - Initial sync (isInitial=true): Creates all accounts without marking deleted ones
 * - Incremental sync (isInitial=false): Updates existing and marks deleted accounts
 */
class SyncCpanelAccountsAction
{
    use AsAction;

    public function __construct(
        private SshConnectionManager $sshManager
    ) {}

    /**
     * Synchronize cPanel accounts from remote server
     *
     * @param  Host  $host  The cPanel server to sync from
     * @param  bool  $isInitial  Whether this is the first sync (doesn't mark deleted accounts)
     * @return array Statistics: ['created' => int, 'updated' => int, 'suspended' => int, 'deleted' => int]
     *
     * @throws Exception If SSH connection fails or command execution fails
     */
    public function handle(Host $host, bool $isInitial = false): array
    {
        if ($host->panel !== PanelType::CPANEL) {
            throw new \InvalidArgumentException("Host {$host->fqdn} is not a cPanel server (panel: {$host->panel->value})");
        }

        Log::info('Starting cPanel accounts sync', [
            'host_id' => $host->id,
            'host_fqdn' => $host->fqdn,
            'is_initial' => $isInitial,
        ]);

        $stats = [
            'created' => 0,
            'updated' => 0,
            'suspended' => 0,
            'deleted' => 0,
        ];

        try {
            // Fetch accounts from remote server
            $remoteAccounts = $this->fetchRemoteAccounts($host);

            // Mark deleted accounts if incremental sync
            if (! $isInitial) {
                $stats['deleted'] = $this->markDeletedAccounts($host, $remoteAccounts);
            }

            // Process accounts in chunks if needed
            $accountsToProcess = $remoteAccounts;
            $chunkSize = config('unblock.sync.chunk_size', 500);

            if (count($accountsToProcess) > $chunkSize) {
                $chunks = array_chunk($accountsToProcess, $chunkSize);
                foreach ($chunks as $chunk) {
                    $chunkStats = $this->processAccounts($host, $chunk);
                    $stats['created'] += $chunkStats['created'];
                    $stats['updated'] += $chunkStats['updated'];
                    $stats['suspended'] += $chunkStats['suspended'];
                }
            } else {
                $chunkStats = $this->processAccounts($host, $accountsToProcess);
                $stats['created'] += $chunkStats['created'];
                $stats['updated'] += $chunkStats['updated'];
                $stats['suspended'] += $chunkStats['suspended'];
            }

            Log::info('cPanel accounts sync completed', array_merge(['host_fqdn' => $host->fqdn], $stats));

            return $stats;

        } catch (Exception $e) {
            Log::error('cPanel accounts sync failed', [
                'host_id' => $host->id,
                'host_fqdn' => $host->fqdn,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Fetch accounts from cPanel server via SSH
     */
    private function fetchRemoteAccounts(Host $host): array
    {
        $session = $this->sshManager->createSession($host);

        try {
            // Execute whmapi1 listaccts to get all accounts
            $command = 'whmapi1 listaccts --output=json';
            $output = $session->execute($command);

            // Parse JSON response
            $data = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Failed to parse whmapi1 JSON response: '.json_last_error_msg());
            }

            if (! isset($data['data']['acct'])) {
                throw new Exception('Invalid whmapi1 response structure: missing data.acct');
            }

            $accounts = $data['data']['acct'];

            // Fetch domains for each account using uapi
            foreach ($accounts as &$accountData) {
                $username = $accountData['user'];
                $accountData['domains'] = $this->fetchAccountDomains($session, $username);
            }

            return $accounts;

        } finally {
            $session->cleanup();
        }
    }

    /**
     * Fetch all domains for a specific account using uapi
     */
    private function fetchAccountDomains($session, string $username): array
    {
        try {
            // Execute uapi DomainInfo list_domains for the specific user
            $command = "uapi --user={$username} --output=json DomainInfo list_domains";
            $output = $session->execute($command);

            // Parse JSON response
            $data = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Failed to parse uapi JSON response for user domains', [
                    'username' => $username,
                    'error' => json_last_error_msg(),
                ]);

                return [];
            }

            if (! isset($data['result']['data'])) {
                Log::warning('Invalid uapi response structure for user domains', [
                    'username' => $username,
                ]);

                return [];
            }

            $domainData = $data['result']['data'];
            $allDomains = [];

            // Collect main domain
            if (! empty($domainData['main_domain'])) {
                $allDomains[] = $domainData['main_domain'];
            }

            // Collect addon domains
            if (! empty($domainData['addon_domains']) && is_array($domainData['addon_domains'])) {
                $allDomains = array_merge($allDomains, $domainData['addon_domains']);
            }

            // Collect parked domains (optional - they point to main domain)
            if (! empty($domainData['parked_domains']) && is_array($domainData['parked_domains'])) {
                $allDomains = array_merge($allDomains, $domainData['parked_domains']);
            }

            // Note: sub_domains are usually not needed for account-level sync
            // but can be added if required:
            // if (!empty($domainData['sub_domains']) && is_array($domainData['sub_domains'])) {
            //     $allDomains = array_merge($allDomains, $domainData['sub_domains']);
            // }

            return array_unique($allDomains);

        } catch (Exception $e) {
            Log::warning('Failed to fetch domains for cPanel user', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Mark accounts as deleted if they no longer exist on the server
     */
    private function markDeletedAccounts(Host $host, array $remoteAccounts): int
    {
        $remoteUsernames = array_column($remoteAccounts, 'user');

        $marked = Account::where('host_id', $host->id)
            ->whereNull('deleted_at')
            ->whereNotIn('username', $remoteUsernames)
            ->update(['deleted_at' => now()]);

        return $marked;
    }

    /**
     * Process accounts and create/update them in database
     */
    private function processAccounts(Host $host, array $accounts): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'suspended' => 0,
        ];

        foreach ($accounts as $accountData) {
            $accountStats = $this->processAccount($host, $accountData);
            $stats['created'] += $accountStats['created'];
            $stats['updated'] += $accountStats['updated'];
            $stats['suspended'] += $accountStats['suspended'];
        }

        return $stats;
    }

    /**
     * Process a single account
     */
    private function processAccount(Host $host, array $accountData): array
    {
        $username = $accountData['user'];
        $domain = $accountData['domain'];
        $owner = $accountData['owner'] ?? null;
        $isSuspended = ($accountData['suspended'] ?? 0) == 1;

        // Check if account exists
        $existing = Account::where('host_id', $host->id)
            ->where('username', $username)
            ->first();

        // Update or create account
        $account = Account::updateOrCreate(
            [
                'host_id' => $host->id,
                'username' => $username,
            ],
            [
                'domain' => $domain,
                'owner' => $owner,
                'suspended_at' => $isSuspended ? now() : null,
                'deleted_at' => null, // Reactivate if was deleted
                'last_synced_at' => now(),
            ]
        );

        $stats = [
            'created' => $existing ? 0 : 1,
            'updated' => $existing ? 1 : 0,
            'suspended' => $isSuspended ? 1 : 0,
        ];

        // Sync primary domain
        $this->syncDomain($account, $domain, 'primary');

        // Sync addon domains if present
        if (! empty($accountData['domains'])) {
            foreach ($accountData['domains'] as $addonDomain) {
                if ($addonDomain !== $domain) { // Skip primary domain
                    $this->syncDomain($account, $addonDomain, 'addon');
                }
            }
        }

        return $stats;
    }

    /**
     * Sync a domain for an account
     */
    private function syncDomain(Account $account, string $domainName, string $type): void
    {
        Domain::updateOrCreate(
            [
                'domain_name' => strtolower(trim($domainName)),
            ],
            [
                'account_id' => $account->id,
                'type' => $type,
            ]
        );
    }
}
