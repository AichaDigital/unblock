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
 * Sync DirectAdmin Accounts Action
 *
 * Synchronizes hosting accounts and domains from a DirectAdmin server to the local database.
 * Reads user configuration files from /usr/local/directadmin/data/users/ directory structure.
 *
 * Execution modes:
 * - Initial sync (isInitial=true): Creates all accounts without marking deleted ones
 * - Incremental sync (isInitial=false): Updates existing and marks deleted accounts
 */
class SyncDirectAdminAccountsAction
{
    use AsAction;

    private const DA_USERS_PATH = '/usr/local/directadmin/data/users';

    public function __construct(
        private SshConnectionManager $sshManager
    ) {}

    /**
     * Synchronize DirectAdmin accounts from remote server
     *
     * @param  Host  $host  The DirectAdmin server to sync from
     * @param  bool  $isInitial  Whether this is the first sync (doesn't mark deleted accounts)
     * @return array Statistics: ['created' => int, 'updated' => int, 'suspended' => int, 'deleted' => int]
     *
     * @throws Exception If SSH connection fails or command execution fails
     */
    public function handle(Host $host, bool $isInitial = false): array
    {
        if ($host->panel !== PanelType::DIRECTADMIN) {
            throw new \InvalidArgumentException("Host {$host->fqdn} is not a DirectAdmin server (panel: {$host->panel->value})");
        }

        Log::info('Starting DirectAdmin accounts sync', [
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

            Log::info('DirectAdmin accounts sync completed', array_merge(['host_fqdn' => $host->fqdn], $stats));

            return $stats;

        } catch (Exception $e) {
            Log::error('DirectAdmin accounts sync failed', [
                'host_id' => $host->id,
                'host_fqdn' => $host->fqdn,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Fetch accounts from DirectAdmin server via SSH
     */
    private function fetchRemoteAccounts(Host $host): array
    {
        $session = $this->sshManager->createSession($host);

        try {
            // List all user directories
            $listCommand = 'ls -1 '.self::DA_USERS_PATH;
            $usersOutput = $session->execute($listCommand);

            $usernames = array_filter(
                explode("\n", trim($usersOutput)),
                fn ($username) => ! empty($username) && $username !== 'admin'
            );

            $accounts = [];

            foreach ($usernames as $username) {
                $accountData = $this->fetchAccountData($session, $username);
                if ($accountData) {
                    $accounts[] = $accountData;
                }
            }

            return $accounts;

        } finally {
            $session->cleanup();
        }
    }

    /**
     * Fetch data for a single account from DirectAdmin
     */
    private function fetchAccountData($session, string $username): ?array
    {
        try {
            $userPath = self::DA_USERS_PATH."/{$username}";

            // Read user.conf file
            $userConfCommand = "cat {$userPath}/user.conf 2>/dev/null || echo ''";
            $userConfOutput = $session->execute($userConfCommand);

            if (empty($userConfOutput)) {
                return null;
            }

            // Parse user.conf (key=value format)
            $userConf = $this->parseUserConf($userConfOutput);

            // Read domains.list file
            $domainsCommand = "cat {$userPath}/domains.list 2>/dev/null || echo ''";
            $domainsOutput = $session->execute($domainsCommand);

            $domains = array_filter(
                explode("\n", trim($domainsOutput)),
                fn ($domain) => ! empty($domain)
            );

            // Get suspension status from user.conf
            $isSuspended = isset($userConf['suspended']) && $userConf['suspended'] === 'yes';

            return [
                'username' => $username,
                'domain' => $userConf['domain'] ?? ($domains[0] ?? null),
                'owner' => $userConf['owner'] ?? $userConf['email'] ?? null,
                'suspended' => $isSuspended,
                'domains' => $domains,
            ];

        } catch (Exception $e) {
            Log::warning('Failed to fetch DirectAdmin account data', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Parse DirectAdmin user.conf file (key=value format)
     */
    private function parseUserConf(string $content): array
    {
        $config = [];
        $lines = explode("\n", trim($content));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $config[trim($key)] = trim($value);
        }

        return $config;
    }

    /**
     * Mark accounts as deleted if they no longer exist on the server
     */
    private function markDeletedAccounts(Host $host, array $remoteAccounts): int
    {
        $remoteUsernames = array_column($remoteAccounts, 'username');

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
        $username = $accountData['username'];
        $domain = $accountData['domain'];
        $owner = $accountData['owner'];
        $isSuspended = $accountData['suspended'];

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

        // Sync domains
        if (! empty($accountData['domains'])) {
            foreach ($accountData['domains'] as $index => $domainName) {
                $type = ($index === 0) ? 'primary' : 'addon';
                $this->syncDomain($account, $domainName, $type);
            }
        } elseif ($domain) {
            // Fallback: sync at least the primary domain
            $this->syncDomain($account, $domain, 'primary');
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
