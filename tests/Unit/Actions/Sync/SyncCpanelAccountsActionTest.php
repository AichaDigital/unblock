<?php

declare(strict_types=1);

use App\Actions\Sync\SyncCpanelAccountsAction;
use App\Models\{Account, Domain, Host};
use App\Services\{SshConnectionManager, SshSession};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a cPanel host
    $this->host = Host::factory()->create([
        'panel' => 'cpanel',
        'fqdn' => 'cpanel.example.com',
        'port_ssh' => 22,
        'hash' => "-----BEGIN OPENSSH PRIVATE KEY-----\ntest_key\n-----END OPENSSH PRIVATE KEY-----",
    ]);

    // Mock SSH Manager and Session
    $this->mockSession = mock(SshSession::class);
    $this->mockSshManager = mock(SshConnectionManager::class);

    $this->mockSshManager
        ->shouldReceive('createSession')
        ->with($this->host)
        ->andReturn($this->mockSession);

    $this->mockSession
        ->shouldReceive('cleanup')
        ->andReturnNull();

    // Create action instance with mocked SSH manager
    $this->action = new SyncCpanelAccountsAction($this->mockSshManager);

    // Helper function to create default uapi response for a domain
    $this->createUapiResponse = function (string $mainDomain, array $addonDomains = []) {
        return json_encode([
            'result' => [
                'data' => [
                    'main_domain' => $mainDomain,
                    'addon_domains' => $addonDomains,
                    'parked_domains' => [],
                    'sub_domains' => [],
                ],
            ],
        ]);
    };

    // Helper to setup mock for accounts with automatic uapi responses
    $this->mockAccountsSync = function (array $accounts, array $domainMap = []) {
        $whmapi1Response = json_encode([
            'data' => ['acct' => $accounts],
        ]);

        $this->mockSession
            ->shouldReceive('execute')
            ->andReturnUsing(function ($command) use ($whmapi1Response, $domainMap, $accounts) {
                if ($command === 'whmapi1 listaccts --output=json') {
                    return $whmapi1Response;
                }

                // Match uapi DomainInfo commands
                if (preg_match('/uapi --user=(\w+) --output=json DomainInfo list_domains/', $command, $matches)) {
                    $username = $matches[1];

                    // If domain map provided, use it
                    if (isset($domainMap[$username])) {
                        return ($this->createUapiResponse)($domainMap[$username]['main'], $domainMap[$username]['addons'] ?? []);
                    }

                    // Otherwise, find the account domain from accounts array
                    foreach ($accounts as $account) {
                        if ($account['user'] === $username) {
                            return ($this->createUapiResponse)($account['domain'], []);
                        }
                    }

                    // Fallback: empty response
                    return ($this->createUapiResponse)('', []);
                }

                throw new \Exception("Unexpected SSH command: {$command}");
            });
    };
});

describe('SyncCpanelAccountsAction', function () {

    describe('handle() - validation', function () {
        it('throws exception if host is not cPanel', function () {
            $daHost = Host::factory()->create(['panel' => 'directadmin']);

            expect(fn () => $this->action->handle($daHost))
                ->toThrow(\InvalidArgumentException::class, 'not a cPanel server');
        });
    });

    describe('handle() - initial sync mode', function () {
        it('creates new accounts from whmapi1 response', function () {
            // Mock whmapi1 listaccts response
            $whmapi1Response = json_encode([
                'data' => [
                    'acct' => [
                        [
                            'user' => 'user1',
                            'domain' => 'user1.com',
                            'owner' => 'admin',
                            'suspended' => 0,
                        ],
                        [
                            'user' => 'user2',
                            'domain' => 'user2.com',
                            'owner' => 'admin',
                            'suspended' => 1,
                        ],
                    ],
                ],
            ]);

            // Mock uapi DomainInfo list_domains responses
            $uapiUser1Response = json_encode([
                'result' => [
                    'data' => [
                        'main_domain' => 'user1.com',
                        'addon_domains' => [],
                        'parked_domains' => [],
                        'sub_domains' => [],
                    ],
                ],
            ]);

            $uapiUser2Response = json_encode([
                'result' => [
                    'data' => [
                        'main_domain' => 'user2.com',
                        'addon_domains' => [],
                        'parked_domains' => [],
                        'sub_domains' => [],
                    ],
                ],
            ]);

            // Setup mock expectations
            $this->mockSession
                ->shouldReceive('execute')
                ->with('whmapi1 listaccts --output=json')
                ->once()
                ->andReturn($whmapi1Response);

            $this->mockSession
                ->shouldReceive('execute')
                ->with('uapi --user=user1 --output=json DomainInfo list_domains')
                ->once()
                ->andReturn($uapiUser1Response);

            $this->mockSession
                ->shouldReceive('execute')
                ->with('uapi --user=user2 --output=json DomainInfo list_domains')
                ->once()
                ->andReturn($uapiUser2Response);

            // Execute initial sync
            $stats = $this->action->handle($this->host, isInitial: true);

            // Assert accounts created
            expect($stats['created'])->toBe(2)
                ->and($stats['updated'])->toBe(0)
                ->and($stats['suspended'])->toBe(1)
                ->and($stats['deleted'])->toBe(0);

            // Verify accounts in database
            $account1 = Account::where('username', 'user1')->first();
            expect($account1)->not->toBeNull()
                ->and($account1->host_id)->toBe($this->host->id)
                ->and($account1->domain)->toBe('user1.com')
                ->and($account1->owner)->toBe('admin')
                ->and($account1->suspended_at)->toBeNull()
                ->and($account1->deleted_at)->toBeNull()
                ->and($account1->last_synced_at)->not->toBeNull();

            $account2 = Account::where('username', 'user2')->first();
            expect($account2->suspended_at)->not->toBeNull();
        });

        it('does NOT mark deleted accounts in initial mode', function () {
            // Create existing account that won't be in sync
            Account::factory()->create([
                'host_id' => $this->host->id,
                'username' => 'old_user',
                'domain' => 'old.com',
            ]);

            // Mock whmapi1 response without old_user
            $whmapi1Response = json_encode([
                'data' => [
                    'acct' => [
                        [
                            'user' => 'new_user',
                            'domain' => 'new.com',
                            'owner' => 'admin',
                            'suspended' => 0,
                        ],
                    ],
                ],
            ]);

            $uapiResponse = ($this->createUapiResponse)('new.com', []);

            $this->mockSession
                ->shouldReceive('execute')
                ->with('whmapi1 listaccts --output=json')
                ->once()
                ->andReturn($whmapi1Response);

            $this->mockSession
                ->shouldReceive('execute')
                ->with('uapi --user=new_user --output=json DomainInfo list_domains')
                ->once()
                ->andReturn($uapiResponse);

            // Execute initial sync
            $stats = $this->action->handle($this->host, isInitial: true);

            // Verify old account NOT marked as deleted
            $oldAccount = Account::where('username', 'old_user')->first();
            expect($oldAccount->deleted_at)->toBeNull()
                ->and($stats['deleted'])->toBe(0);
        });

        it('syncs primary and addon domains', function () {
            // Mock whmapi1 listaccts response
            $whmapi1Response = json_encode([
                'data' => [
                    'acct' => [
                        [
                            'user' => 'user1',
                            'domain' => 'user1.com',
                            'owner' => 'admin',
                            'suspended' => 0,
                        ],
                    ],
                ],
            ]);

            // Mock uapi DomainInfo list_domains with addon domains
            $uapiResponse = json_encode([
                'result' => [
                    'data' => [
                        'main_domain' => 'user1.com',
                        'addon_domains' => ['addon1.com', 'addon2.com'],
                        'parked_domains' => [],
                        'sub_domains' => [],
                    ],
                ],
            ]);

            $this->mockSession
                ->shouldReceive('execute')
                ->with('whmapi1 listaccts --output=json')
                ->once()
                ->andReturn($whmapi1Response);

            $this->mockSession
                ->shouldReceive('execute')
                ->with('uapi --user=user1 --output=json DomainInfo list_domains')
                ->once()
                ->andReturn($uapiResponse);

            $this->action->handle($this->host, isInitial: true);

            // Verify domains
            $account = Account::where('username', 'user1')->first();
            expect($account->domains()->count())->toBe(3);

            $primaryDomain = Domain::where('domain_name', 'user1.com')->first();
            expect($primaryDomain->type)->toBe('primary')
                ->and($primaryDomain->account_id)->toBe($account->id);

            $addonDomain = Domain::where('domain_name', 'addon1.com')->first();
            expect($addonDomain->type)->toBe('addon')
                ->and($addonDomain->account_id)->toBe($account->id);

            $addon2Domain = Domain::where('domain_name', 'addon2.com')->first();
            expect($addon2Domain->type)->toBe('addon')
                ->and($addon2Domain->account_id)->toBe($account->id);
        });
    });

    describe('handle() - incremental sync mode', function () {
        it('updates existing accounts', function () {
            // Create existing account
            $existingAccount = Account::factory()->create([
                'host_id' => $this->host->id,
                'username' => 'user1',
                'domain' => 'old-domain.com',
                'owner' => 'old_owner',
                'suspended_at' => now(),
            ]);

            // Mock account sync
            ($this->mockAccountsSync)([
                [
                    'user' => 'user1',
                    'domain' => 'new-domain.com',
                    'owner' => 'new_owner',
                    'suspended' => 0,
                ],
            ]);

            // Execute incremental sync
            $stats = $this->action->handle($this->host, isInitial: false);

            // Assert updated
            expect($stats['created'])->toBe(0)
                ->and($stats['updated'])->toBe(1);

            // Verify account updated
            $existingAccount->refresh();
            expect($existingAccount->domain)->toBe('new-domain.com')
                ->and($existingAccount->owner)->toBe('new_owner')
                ->and($existingAccount->suspended_at)->toBeNull() // Unsuspended
                ->and($existingAccount->last_synced_at)->not->toBeNull();
        });

        it('marks deleted accounts in incremental mode', function () {
            // Create accounts that will be deleted
            Account::factory()->create([
                'host_id' => $this->host->id,
                'username' => 'deleted_user1',
            ]);
            Account::factory()->create([
                'host_id' => $this->host->id,
                'username' => 'deleted_user2',
            ]);

            // Mock account sync without deleted users
            ($this->mockAccountsSync)([
                [
                    'user' => 'active_user',
                    'domain' => 'active.com',
                    'owner' => 'admin',
                    'suspended' => 0,
                ],
            ]);

            // Execute incremental sync
            $stats = $this->action->handle($this->host, isInitial: false);

            // Verify deleted marked
            expect($stats['deleted'])->toBe(2);

            $deletedAccount1 = Account::where('username', 'deleted_user1')->first();
            $deletedAccount2 = Account::where('username', 'deleted_user2')->first();

            expect($deletedAccount1->deleted_at)->not->toBeNull()
                ->and($deletedAccount2->deleted_at)->not->toBeNull();
        });

        it('reactivates previously deleted accounts', function () {
            // Create deleted account
            $deletedAccount = Account::factory()->deleted()->create([
                'host_id' => $this->host->id,
                'username' => 'revived_user',
            ]);

            // Mock account sync with revived account
            ($this->mockAccountsSync)([
                [
                    'user' => 'revived_user',
                    'domain' => 'revived.com',
                    'owner' => 'admin',
                    'suspended' => 0,
                ],
            ]);

            // Execute sync
            $this->action->handle($this->host, isInitial: false);

            // Verify reactivated
            $deletedAccount->refresh();
            expect($deletedAccount->deleted_at)->toBeNull()
                ->and($deletedAccount->domain)->toBe('revived.com');
        });

        it('does not mark accounts from other hosts as deleted', function () {
            $otherHost = Host::factory()->create(['panel' => 'cpanel']);

            Account::factory()->create([
                'host_id' => $otherHost->id,
                'username' => 'other_host_user',
            ]);

            // Mock empty response for current host
            ($this->mockAccountsSync)([]);

            // Execute sync
            $stats = $this->action->handle($this->host, isInitial: false);

            // Verify other host account NOT affected
            $otherAccount = Account::where('username', 'other_host_user')->first();
            expect($otherAccount->deleted_at)->toBeNull()
                ->and($stats['deleted'])->toBe(0);
        });
    });

    describe('handle() - chunking', function () {
        it('processes accounts in chunks when count exceeds chunk_size', function () {
            // Set chunk size to 2 for testing
            config(['unblock.sync.chunk_size' => 2]);

            // Mock 5 accounts (will require 3 chunks)
            $accounts = [];
            for ($i = 1; $i <= 5; $i++) {
                $accounts[] = [
                    'user' => "user{$i}",
                    'domain' => "user{$i}.com",
                    'owner' => 'admin',
                    'suspended' => 0,
                ];
            }

            ($this->mockAccountsSync)($accounts);

            // Execute sync
            $stats = $this->action->handle($this->host, isInitial: true);

            // Verify all accounts processed
            expect($stats['created'])->toBe(5)
                ->and(Account::where('host_id', $this->host->id)->count())->toBe(5);
        });

        it('does not chunk when count is below chunk_size', function () {
            config(['unblock.sync.chunk_size' => 500]);

            // Mock 3 accounts (below chunk size)
            $accounts = [];
            for ($i = 1; $i <= 3; $i++) {
                $accounts[] = [
                    'user' => "user{$i}",
                    'domain' => "user{$i}.com",
                    'owner' => 'admin',
                    'suspended' => 0,
                ];
            }

            ($this->mockAccountsSync)($accounts);

            // Execute sync
            $stats = $this->action->handle($this->host, isInitial: true);

            // Verify all processed
            expect($stats['created'])->toBe(3);
        });
    });

    describe('handle() - error handling', function () {
        it('throws exception when JSON parsing fails', function () {
            $this->mockSession
                ->shouldReceive('execute')
                ->once()
                ->andReturn('invalid json response');

            expect(fn () => $this->action->handle($this->host))
                ->toThrow(\Exception::class, 'Failed to parse whmapi1 JSON response');
        });

        it('throws exception when response structure is invalid', function () {
            $whmapi1Response = json_encode([
                'data' => ['invalid' => 'structure'],
            ]);

            $this->mockSession
                ->shouldReceive('execute')
                ->once()
                ->andReturn($whmapi1Response);

            expect(fn () => $this->action->handle($this->host))
                ->toThrow(\Exception::class, 'Invalid whmapi1 response structure');
        });

        it('propagates SSH connection exceptions', function () {
            $this->mockSession
                ->shouldReceive('execute')
                ->once()
                ->andThrow(new \Exception('SSH connection failed'));

            expect(fn () => $this->action->handle($this->host))
                ->toThrow(\Exception::class, 'SSH connection failed');
        });
    });

    describe('handle() - suspension handling', function () {
        it('marks suspended accounts correctly', function () {
            ($this->mockAccountsSync)([
                [
                    'user' => 'suspended_user',
                    'domain' => 'suspended.com',
                    'owner' => 'admin',
                    'suspended' => 1,
                ],
            ]);

            $stats = $this->action->handle($this->host, isInitial: true);

            expect($stats['suspended'])->toBe(1);

            $account = Account::where('username', 'suspended_user')->first();
            expect($account->suspended_at)->not->toBeNull();
        });

        it('unsuspends accounts when status changes', function () {
            // Create suspended account
            $suspendedAccount = Account::factory()->suspended()->create([
                'host_id' => $this->host->id,
                'username' => 'user1',
            ]);

            // Mock account sync with unsuspended status
            ($this->mockAccountsSync)([
                [
                    'user' => 'user1',
                    'domain' => 'user1.com',
                    'owner' => 'admin',
                    'suspended' => 0,
                ],
            ]);

            $this->action->handle($this->host, isInitial: false);

            $suspendedAccount->refresh();
            expect($suspendedAccount->suspended_at)->toBeNull();
        });
    });

    describe('handle() - user_id preservation', function () {
        it('never touches user_id during sync', function () {
            // Create account with user_id
            $account = Account::factory()->withUser()->create([
                'host_id' => $this->host->id,
                'username' => 'user1',
            ]);

            $originalUserId = $account->user_id;

            // Mock account sync
            ($this->mockAccountsSync)([
                [
                    'user' => 'user1',
                    'domain' => 'updated.com',
                    'owner' => 'admin',
                    'suspended' => 0,
                ],
            ]);

            // Execute sync
            $this->action->handle($this->host, isInitial: false);

            // Verify user_id unchanged
            $account->refresh();
            expect($account->user_id)->toBe($originalUserId)
                ->and($account->domain)->toBe('updated.com'); // But other fields updated
        });
    });

    describe('handle() - statistics accuracy', function () {
        it('returns accurate statistics for mixed operations', function () {
            // Create 2 existing accounts (1 will be updated, 1 deleted)
            Account::factory()->create([
                'host_id' => $this->host->id,
                'username' => 'existing_user',
            ]);
            Account::factory()->create([
                'host_id' => $this->host->id,
                'username' => 'to_be_deleted',
            ]);

            // Mock response: 1 existing (updated), 2 new, 1 suspended
            ($this->mockAccountsSync)([
                ['user' => 'existing_user', 'domain' => 'existing.com', 'owner' => 'admin', 'suspended' => 0],
                ['user' => 'new_user1', 'domain' => 'new1.com', 'owner' => 'admin', 'suspended' => 0],
                ['user' => 'new_user2', 'domain' => 'new2.com', 'owner' => 'admin', 'suspended' => 1],
            ]);

            $stats = $this->action->handle($this->host, isInitial: false);

            expect($stats['created'])->toBe(2)
                ->and($stats['updated'])->toBe(1)
                ->and($stats['suspended'])->toBe(1)
                ->and($stats['deleted'])->toBe(1);
        });
    });
});
