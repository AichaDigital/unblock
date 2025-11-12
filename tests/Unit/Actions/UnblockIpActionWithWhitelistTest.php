<?php

declare(strict_types=1);

use App\Actions\UnblockIpAction;
use App\Models\{BfmWhitelistEntry, Host};
use Tests\Helpers\FirewallServiceStub;

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
    // ✅ Using stub instead of mocks - much cleaner and uses real FirewallService logic
    $firewallService = FirewallServiceStub::ipNotBlocked()
        ->setCommandResponse('whitelist_simple', 'Whitelist added');

    $action = new UnblockIpAction($firewallService);
    $result = $action->handle('1.2.3.4', $this->host->id, 'test-key');

    expect($result['success'])->toBeTrue();
});

test('unblock action for DirectAdmin also handles BFM blacklist and whitelist', function () {
    // ✅ Using stub with IP in permanent deny + BFM operations
    $firewallService = FirewallServiceStub::ipInPermanentDeny('5.6.7.8')
        ->setCommandResponse('whitelist_simple', 'Whitelist added')
        ->setCommandResponse('da_bfm_check', '5.6.7.8') // IP found in BFM blacklist
        ->setCommandResponse('da_bfm_remove', 'Removed')
        ->setCommandResponse('da_bfm_whitelist_add', 'Added to whitelist');

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
    // ✅ IP not blocked, BFM check returns empty (not in blacklist)
    $firewallService = FirewallServiceStub::ipNotBlocked()
        ->setCommandResponse('whitelist_simple', 'Whitelist added')
        ->setCommandResponse('da_bfm_check', '') // NOT in BFM blacklist
        ->setCommandResponse('da_bfm_whitelist_add', 'Added to whitelist');

    $action = new UnblockIpAction($firewallService);
    $result = $action->handle('9.10.11.12', $this->hostDa->id, 'test-key-da');

    expect($result['success'])->toBeTrue();
});

test('unblock action handles BFM failure gracefully without failing whole operation', function () {
    // ✅ Using helper method to create stub with exception for BFM check
    $firewallService = FirewallServiceStub::ipNotBlocked()
        ->setCommandResponse('whitelist_simple', 'Whitelist added')
        ->withExceptionFor('da_bfm_check', new \Exception('BFM service unavailable'));

    $action = new UnblockIpAction($firewallService);
    $result = $action->handle('10.11.12.13', $this->hostDa->id, 'test-key-da');

    // Operation should still succeed even though BFM failed
    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe(__('messages.firewall.ip_unblocked'));
});

test('unblock action respects custom TTL from config', function () {
    // Arrange - Set custom TTL
    config()->set('unblock.simple_mode.whitelist_ttl', 7200); // 2 hours

    // ✅ Using stub - much cleaner
    $firewallService = FirewallServiceStub::ipNotBlocked()
        ->setCommandResponse('whitelist_simple', 'Whitelist added')
        ->setCommandResponse('da_bfm_check', '11.12.13.14')
        ->setCommandResponse('da_bfm_remove', 'Removed')
        ->setCommandResponse('da_bfm_whitelist_add', 'Added');

    $action = new UnblockIpAction($firewallService);
    $result = $action->handle('11.12.13.14', $this->hostDa->id, 'test-key-da');

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
    // ✅ cPanel host - no BFM operations should be attempted
    $firewallService = FirewallServiceStub::ipNotBlocked()
        ->setCommandResponse('whitelist_simple', 'Whitelist added');

    $action = new UnblockIpAction($firewallService);
    $result = $action->handle('12.13.14.15', $this->host->id, 'test-key');

    expect($result['success'])->toBeTrue();

    // Verify no BFM whitelist entry was created (cPanel doesn't use BFM)
    expect(BfmWhitelistEntry::where('ip_address', '12.13.14.15')->count())->toBe(0);
});
