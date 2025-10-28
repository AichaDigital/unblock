<?php

declare(strict_types=1);

use App\Actions\Sync\SyncDirectAdminAccountsAction;
use App\Models\{Account, Domain, Host};
use App\Services\{SshConnectionManager, SshSession};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a DirectAdmin host
    $this->host = Host::factory()->create([
        'panel' => 'directadmin',
        'fqdn' => 'da.example.com',
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
    $this->action = new SyncDirectAdminAccountsAction($this->mockSshManager);
});

describe('SyncDirectAdminAccountsAction', function () {

    describe('handle() - validation', function () {
        it('throws exception if host is not DirectAdmin', function () {
            $cpanelHost = Host::factory()->create(['panel' => 'cpanel']);

            expect(fn () => $this->action->handle($cpanelHost))
                ->toThrow(\InvalidArgumentException::class, 'not a DirectAdmin server');
        });

        it('accepts both directadmin and da panel types', function () {
            $daHost = Host::factory()->create([
                'panel' => 'da',
                'fqdn' => 'da2.example.com',
                'port_ssh' => 22,
                'hash' => "-----BEGIN OPENSSH PRIVATE KEY-----\ntest_key\n-----END OPENSSH PRIVATE KEY-----",
            ]);

            // Mock SSH Manager for the new host
            $mockSession = mock(SshSession::class);
            $this->mockSshManager
                ->shouldReceive('createSession')
                ->with($daHost)
                ->andReturn($mockSession);

            $mockSession
                ->shouldReceive('cleanup')
                ->andReturnNull();

            // Mock responses
            $mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn('');

            // Should not throw and should succeed
            $stats = $this->action->handle($daHost, isInitial: true);

            expect($stats['created'])->toBe(0);
        });
    });

    describe('handle() - initial sync mode', function () {
        it('creates new accounts from DirectAdmin user directories', function () {
            // Mock ls -1 response (list of usernames)
            $this->mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn("user1\nuser2\nadmin");

            // Mock user.conf for user1
            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("domain=user1.com\nemail=user1@user1.com\nowner=admin\nsuspended=no");

            // Mock domains.list for user1
            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/domains.list 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("user1.com\naddon1.com");

            // Mock user.conf for user2 (suspended)
            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user2/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("domain=user2.com\nemail=user2@user2.com\nowner=admin\nsuspended=yes");

            // Mock domains.list for user2
            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user2/domains.list 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn('user2.com');

            // Admin should be skipped, so no mock for admin

            // Execute initial sync
            $stats = $this->action->handle($this->host, isInitial: true);

            // Assert accounts created (2, admin is filtered out)
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
                ->and($account1->deleted_at)->toBeNull();

            $account2 = Account::where('username', 'user2')->first();
            expect($account2->suspended_at)->not->toBeNull();

            // Verify domains for user1
            expect($account1->domains()->count())->toBe(2);
            $primaryDomain = Domain::where('domain_name', 'user1.com')->first();
            expect($primaryDomain->type)->toBe('primary');
            $addonDomain = Domain::where('domain_name', 'addon1.com')->first();
            expect($addonDomain->type)->toBe('addon');
        });

        it('filters out admin user', function () {
            $this->mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn('admin');

            $stats = $this->action->handle($this->host, isInitial: true);

            expect($stats['created'])->toBe(0)
                ->and(Account::where('username', 'admin')->count())->toBe(0);
        });

        it('does NOT mark deleted accounts in initial mode', function () {
            // Create existing account that won't be in sync
            Account::factory()->create([
                'host_id' => $this->host->id,
                'username' => 'old_user',
                'domain' => 'old.com',
            ]);

            // Mock response without old_user
            $this->mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn('new_user');

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/new_user/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("domain=new.com\nemail=new@new.com\nsuspended=no");

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/new_user/domains.list 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn('new.com');

            // Execute initial sync
            $stats = $this->action->handle($this->host, isInitial: true);

            // Verify old account NOT marked as deleted
            $oldAccount = Account::where('username', 'old_user')->first();
            expect($oldAccount->deleted_at)->toBeNull()
                ->and($stats['deleted'])->toBe(0);
        });

        it('handles empty user.conf gracefully', function () {
            $this->mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn("user1\nuser2");

            // user1 has empty user.conf (should be skipped)
            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn('');

            // user2 is valid
            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user2/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("domain=user2.com\nemail=user2@user2.com\nsuspended=no");

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user2/domains.list 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn('user2.com');

            $stats = $this->action->handle($this->host, isInitial: true);

            // Only user2 should be created
            expect($stats['created'])->toBe(1)
                ->and(Account::where('username', 'user1')->count())->toBe(0)
                ->and(Account::where('username', 'user2')->count())->toBe(1);
        });

        it('uses domain from domains.list when user.conf domain is missing', function () {
            $this->mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn('user1');

            // user.conf without domain
            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("email=user1@example.com\nsuspended=no");

            // domains.list has the domain
            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/domains.list 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn('user1.com');

            $stats = $this->action->handle($this->host, isInitial: true);

            $account = Account::where('username', 'user1')->first();
            expect($account->domain)->toBe('user1.com');
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

            // Mock responses
            $this->mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn('user1');

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("domain=new-domain.com\nowner=new_owner\nsuspended=no");

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/domains.list 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn('new-domain.com');

            // Execute incremental sync
            $stats = $this->action->handle($this->host, isInitial: false);

            // Assert updated
            expect($stats['created'])->toBe(0)
                ->and($stats['updated'])->toBe(1);

            // Verify account updated
            $existingAccount->refresh();
            expect($existingAccount->domain)->toBe('new-domain.com')
                ->and($existingAccount->owner)->toBe('new_owner')
                ->and($existingAccount->suspended_at)->toBeNull(); // Unsuspended
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

            // Mock response without these accounts
            $this->mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn('active_user');

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/active_user/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("domain=active.com\nsuspended=no");

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/active_user/domains.list 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn('active.com');

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

            // Mock responses
            $this->mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn('revived_user');

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/revived_user/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("domain=revived.com\nowner=admin\nsuspended=no");

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/revived_user/domains.list 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn('revived.com');

            // Execute sync
            $this->action->handle($this->host, isInitial: false);

            // Verify reactivated
            $deletedAccount->refresh();
            expect($deletedAccount->deleted_at)->toBeNull()
                ->and($deletedAccount->domain)->toBe('revived.com');
        });

        it('does not mark accounts from other hosts as deleted', function () {
            $otherHost = Host::factory()->create(['panel' => 'directadmin']);

            Account::factory()->create([
                'host_id' => $otherHost->id,
                'username' => 'other_host_user',
            ]);

            // Mock empty response for current host
            $this->mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn('');

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

            // Mock 5 users
            $users = [];
            for ($i = 1; $i <= 5; $i++) {
                $users[] = "user{$i}";
            }

            $this->mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn(implode("\n", $users));

            // Mock responses for each user
            for ($i = 1; $i <= 5; $i++) {
                $this->mockSession
                    ->shouldReceive('execute')
                    ->with("cat /usr/local/directadmin/data/users/user{$i}/user.conf 2>/dev/null || echo ''")
                    ->once()
                    ->andReturn("domain=user{$i}.com\nsuspended=no");

                $this->mockSession
                    ->shouldReceive('execute')
                    ->with("cat /usr/local/directadmin/data/users/user{$i}/domains.list 2>/dev/null || echo ''")
                    ->once()
                    ->andReturn("user{$i}.com");
            }

            // Execute sync
            $stats = $this->action->handle($this->host, isInitial: true);

            // Verify all accounts processed
            expect($stats['created'])->toBe(5)
                ->and(Account::where('host_id', $this->host->id)->count())->toBe(5);
        });
    });

    describe('handle() - error handling', function () {
        it('propagates SSH connection exceptions', function () {
            $this->mockSession
                ->shouldReceive('execute')
                ->once()
                ->andThrow(new \Exception('SSH connection failed'));

            expect(fn () => $this->action->handle($this->host))
                ->toThrow(\Exception::class, 'SSH connection failed');
        });

        it('logs warning and continues when individual account fetch fails', function () {
            $this->mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn("user1\nuser2");

            // user1 throws exception
            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andThrow(new \Exception('Permission denied'));

            // user2 succeeds
            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user2/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("domain=user2.com\nsuspended=no");

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user2/domains.list 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn('user2.com');

            // Execute sync
            $stats = $this->action->handle($this->host, isInitial: true);

            // user2 should be created, user1 skipped
            expect($stats['created'])->toBe(1)
                ->and(Account::where('username', 'user2')->count())->toBe(1)
                ->and(Account::where('username', 'user1')->count())->toBe(0);
        });
    });

    describe('handle() - suspension handling', function () {
        it('marks suspended accounts correctly', function () {
            $this->mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn('suspended_user');

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/suspended_user/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("domain=suspended.com\nsuspended=yes");

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/suspended_user/domains.list 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn('suspended.com');

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

            // Mock responses with unsuspended status
            $this->mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn('user1');

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("domain=user1.com\nsuspended=no");

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/domains.list 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn('user1.com');

            $this->action->handle($this->host, isInitial: false);

            $suspendedAccount->refresh();
            expect($suspendedAccount->suspended_at)->toBeNull();
        });

        it('treats missing suspended key as not suspended', function () {
            $this->mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn('user1');

            // No suspended key in user.conf
            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("domain=user1.com\nowner=admin");

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/domains.list 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn('user1.com');

            $stats = $this->action->handle($this->host, isInitial: true);

            $account = Account::where('username', 'user1')->first();
            expect($account->suspended_at)->toBeNull()
                ->and($stats['suspended'])->toBe(0);
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

            // Mock responses
            $this->mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn('user1');

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("domain=updated.com\nsuspended=no");

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/domains.list 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn('updated.com');

            // Execute sync
            $this->action->handle($this->host, isInitial: false);

            // Verify user_id unchanged
            $account->refresh();
            expect($account->user_id)->toBe($originalUserId)
                ->and($account->domain)->toBe('updated.com'); // But other fields updated
        });
    });

    describe('handle() - domain parsing', function () {
        it('parses multiple domains correctly', function () {
            $this->mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn('user1');

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("domain=user1.com\nsuspended=no");

            // Multiple domains in domains.list
            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/domains.list 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("user1.com\naddon1.com\naddon2.com\naddon3.com");

            $this->action->handle($this->host, isInitial: true);

            $account = Account::where('username', 'user1')->first();
            expect($account->domains()->count())->toBe(4);

            // First is primary
            $primaryDomain = Domain::where('domain_name', 'user1.com')->first();
            expect($primaryDomain->type)->toBe('primary');

            // Rest are addons
            $addonDomains = Domain::where('account_id', $account->id)
                ->where('type', 'addon')
                ->count();
            expect($addonDomains)->toBe(3);
        });

        it('handles empty domains.list', function () {
            $this->mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn('user1');

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("domain=user1.com\nsuspended=no");

            // Empty domains.list
            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/domains.list 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn('');

            $this->action->handle($this->host, isInitial: true);

            // Should still create account with domain from user.conf
            $account = Account::where('username', 'user1')->first();
            expect($account)->not->toBeNull()
                ->and($account->domain)->toBe('user1.com');

            // Should create at least primary domain
            $domain = Domain::where('domain_name', 'user1.com')->first();
            expect($domain)->not->toBeNull()
                ->and($domain->type)->toBe('primary');
        });

        it('normalizes domain names to lowercase', function () {
            $this->mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn('user1');

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("domain=USER1.COM\nsuspended=no");

            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/user1/domains.list 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("USER1.COM\nADDON1.COM");

            $this->action->handle($this->host, isInitial: true);

            // Verify domains stored as lowercase
            $domains = Domain::whereIn('domain_name', ['user1.com', 'addon1.com'])->get();
            expect($domains)->toHaveCount(2);
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

            // Mock: 1 existing (updated), 2 new (1 suspended)
            $this->mockSession
                ->shouldReceive('execute')
                ->with('ls -1 /usr/local/directadmin/data/users')
                ->once()
                ->andReturn("existing_user\nnew_user1\nnew_user2");

            // existing_user
            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/existing_user/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("domain=existing.com\nsuspended=no");
            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/existing_user/domains.list 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn('existing.com');

            // new_user1
            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/new_user1/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("domain=new1.com\nsuspended=no");
            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/new_user1/domains.list 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn('new1.com');

            // new_user2 (suspended)
            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/new_user2/user.conf 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn("domain=new2.com\nsuspended=yes");
            $this->mockSession
                ->shouldReceive('execute')
                ->with('cat /usr/local/directadmin/data/users/new_user2/domains.list 2>/dev/null || echo \'\'')
                ->once()
                ->andReturn('new2.com');

            $stats = $this->action->handle($this->host, isInitial: false);

            expect($stats['created'])->toBe(2)
                ->and($stats['updated'])->toBe(1)
                ->and($stats['suspended'])->toBe(1)
                ->and($stats['deleted'])->toBe(1);
        });
    });
});
