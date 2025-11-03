<?php

declare(strict_types=1);

use App\Actions\UnblockIpAction;
use App\Models\{BfmWhitelistEntry, Host};
use App\Services\FirewallService;
use Mockery\MockInterface;

beforeEach(function () {
    $this->host = Host::factory()->create([
        'panel' => 'cpanel',
        'hash' => 'test-key',
    ]);

    $this->hostDa = Host::factory()->create([
        'panel' => 'directadmin',
        'hash' => 'test-key-da',
    ]);
});

test('unblock action calls csf unblock and whitelist separately', function () {
    /** @var FirewallService&MockInterface */
    $firewallService = mock(FirewallService::class);

    // 1. Expect unblock command (without whitelist)
    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->with(
            \Mockery::type(Host::class),
            'test-key',
            'unblock',
            '1.2.3.4'
        )
        ->andReturn('IP removed');

    // 2. Expect whitelist_simple command (separate)
    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->with(
            \Mockery::type(Host::class),
            'test-key',
            'whitelist_simple',
            '1.2.3.4'
        )
        ->andReturn('Whitelist added');

    $action = new UnblockIpAction($firewallService);
    $result = $action->handle('1.2.3.4', $this->host->id, 'test-key');

    expect($result['success'])->toBeTrue();
});

test('unblock action for DirectAdmin also handles BFM blacklist and whitelist', function () {
    /** @var FirewallService&MockInterface */
    $firewallService = mock(FirewallService::class);

    // 1. CSF unblock
    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->with(\Mockery::type(Host::class), 'test-key-da', 'unblock', '5.6.7.8')
        ->andReturn('IP removed');

    // 2. CSF whitelist
    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->with(\Mockery::type(Host::class), 'test-key-da', 'whitelist_simple', '5.6.7.8')
        ->andReturn('Whitelist added');

    // 3. Check BFM blacklist
    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->with(\Mockery::type(Host::class), 'test-key-da', 'da_bfm_check', '5.6.7.8')
        ->andReturn('5.6.7.8'); // IP found in BFM blacklist

    // 4. Remove from BFM blacklist
    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->with(\Mockery::type(Host::class), 'test-key-da', 'da_bfm_remove', '5.6.7.8')
        ->andReturn('Removed');

    // 5. Add to BFM whitelist
    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->with(\Mockery::type(Host::class), 'test-key-da', 'da_bfm_whitelist_add', '5.6.7.8')
        ->andReturn('Added to whitelist');

    $action = new UnblockIpAction($firewallService);
    $result = $action->handle('5.6.7.8', $this->hostDa->id, 'test-key-da');

    expect($result['success'])->toBeTrue();

    // Verify BFM whitelist entry was created in database
    expect(BfmWhitelistEntry::where('ip_address', '5.6.7.8')->count())->toBe(1);

    $entry = BfmWhitelistEntry::where('ip_address', '5.6.7.8')->first();
    expect($entry->host_id)->toBe($this->hostDa->id);
    expect($entry->expires_at)->not->toBeNull();
});

test('unblock action for DirectAdmin skips BFM removal if IP not in blacklist', function () {
    /** @var FirewallService&MockInterface */
    $firewallService = mock(FirewallService::class);

    // CSF operations
    $firewallService->shouldReceive('checkProblems')
        ->with(\Mockery::type(Host::class), 'test-key-da', 'unblock', '9.10.11.12')
        ->andReturn('IP removed');

    $firewallService->shouldReceive('checkProblems')
        ->with(\Mockery::type(Host::class), 'test-key-da', 'whitelist_simple', '9.10.11.12')
        ->andReturn('Whitelist added');

    // BFM check returns empty (not in blacklist)
    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->with(\Mockery::type(Host::class), 'test-key-da', 'da_bfm_check', '9.10.11.12')
        ->andReturn(''); // NOT in BFM blacklist

    // Should NOT call da_bfm_remove
    $firewallService->shouldNotReceive('checkProblems')
        ->with(\Mockery::type(Host::class), 'test-key-da', 'da_bfm_remove', \Mockery::any());

    // But SHOULD still add to BFM whitelist
    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->with(\Mockery::type(Host::class), 'test-key-da', 'da_bfm_whitelist_add', '9.10.11.12')
        ->andReturn('Added to whitelist');

    $action = new UnblockIpAction($firewallService);
    $result = $action->handle('9.10.11.12', $this->hostDa->id, 'test-key-da');

    expect($result['success'])->toBeTrue();
});

test('unblock action handles BFM failure gracefully without failing whole operation', function () {
    /** @var FirewallService&MockInterface */
    $firewallService = mock(FirewallService::class);

    // CSF operations succeed
    $firewallService->shouldReceive('checkProblems')
        ->with(\Mockery::type(Host::class), 'test-key-da', 'unblock', '10.11.12.13')
        ->andReturn('IP removed');

    $firewallService->shouldReceive('checkProblems')
        ->with(\Mockery::type(Host::class), 'test-key-da', 'whitelist_simple', '10.11.12.13')
        ->andReturn('Whitelist added');

    // BFM check throws exception
    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->with(\Mockery::type(Host::class), 'test-key-da', 'da_bfm_check', '10.11.12.13')
        ->andThrow(new \Exception('BFM service unavailable'));

    $action = new UnblockIpAction($firewallService);
    $result = $action->handle('10.11.12.13', $this->hostDa->id, 'test-key-da');

    // Operation should still succeed even though BFM failed
    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe(__('messages.firewall.ip_unblocked'));
});

test('unblock action respects custom TTL from config', function () {
    // Arrange - Set custom TTL
    config()->set('unblock.simple_mode.whitelist_ttl', 7200); // 2 hours

    /** @var FirewallService&MockInterface */
    $firewallService = mock(FirewallService::class);

    // Mock all CSF operations
    $firewallService->shouldReceive('checkProblems')
        ->with(\Mockery::type(Host::class), 'test-key-da', 'unblock', '11.12.13.14')
        ->andReturn('IP removed');

    $firewallService->shouldReceive('checkProblems')
        ->with(\Mockery::type(Host::class), 'test-key-da', 'whitelist_simple', '11.12.13.14')
        ->andReturn('Whitelist added');

    // Mock BFM operations
    $firewallService->shouldReceive('checkProblems')
        ->with(\Mockery::type(Host::class), 'test-key-da', 'da_bfm_check', '11.12.13.14')
        ->andReturn('11.12.13.14');

    $firewallService->shouldReceive('checkProblems')
        ->with(\Mockery::type(Host::class), 'test-key-da', 'da_bfm_remove', '11.12.13.14')
        ->andReturn('Removed');

    $firewallService->shouldReceive('checkProblems')
        ->with(\Mockery::type(Host::class), 'test-key-da', 'da_bfm_whitelist_add', '11.12.13.14')
        ->andReturn('Added');

    // Act
    $action = new UnblockIpAction($firewallService);
    $result = $action->handle('11.12.13.14', $this->hostDa->id, 'test-key-da');

    // Assert
    expect($result['success'])->toBeTrue();

    // Verify TTL was applied correctly
    $entry = BfmWhitelistEntry::where('ip_address', '11.12.13.14')->first();
    expect($entry)->not->toBeNull();

    $expectedExpiration = now()->addSeconds(7200);
    $actualExpiration = $entry->expires_at;

    // Allow 2 seconds tolerance for test execution time
    expect($actualExpiration->diffInSeconds($expectedExpiration, false))->toBeLessThanOrEqual(2);
});

test('unblock action for cpanel does not attempt BFM operations', function () {
    /** @var FirewallService&MockInterface */
    $firewallService = mock(FirewallService::class);

    // CSF operations
    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->with(\Mockery::type(Host::class), 'test-key', 'unblock', '12.13.14.15')
        ->andReturn('IP removed');

    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->with(\Mockery::type(Host::class), 'test-key', 'whitelist_simple', '12.13.14.15')
        ->andReturn('Whitelist added');

    // Should NOT receive any BFM-related calls
    $firewallService->shouldNotReceive('checkProblems')
        ->with(\Mockery::any(), \Mockery::any(), 'da_bfm_check', \Mockery::any());

    $firewallService->shouldNotReceive('checkProblems')
        ->with(\Mockery::any(), \Mockery::any(), 'da_bfm_remove', \Mockery::any());

    $firewallService->shouldNotReceive('checkProblems')
        ->with(\Mockery::any(), \Mockery::any(), 'da_bfm_whitelist_add', \Mockery::any());

    $action = new UnblockIpAction($firewallService);
    $result = $action->handle('12.13.14.15', $this->host->id, 'test-key');

    expect($result['success'])->toBeTrue();

    // Verify no BFM whitelist entry was created
    expect(BfmWhitelistEntry::where('ip_address', '12.13.14.15')->count())->toBe(0);
});
