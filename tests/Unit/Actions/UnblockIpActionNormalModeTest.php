<?php

declare(strict_types=1);

use App\Actions\UnblockIpActionNormalMode;
use App\Enums\PanelType;
use App\Models\{BfmWhitelistEntry, Host};
use App\Services\Firewall\FirewallAnalysisResult;
use App\Services\SshConnectionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Helpers\FirewallServiceStub;

uses(RefreshDatabase::class);

test('action successfully unblocks IP for cPanel host', function () {
    config()->set('unblock.whitelist_ttl', 86400);

    $host = Host::factory()->create([
        'panel' => PanelType::CPANEL,
    ]);

    // ✅ Using stub instead of mock
    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->allows('generateSshKey')->andReturn('/tmp/test_key');
    $sshManager->allows('removeSshKey')->andReturn(true);

    $firewallService = FirewallServiceStub::ipNotBlocked()
        ->setCommandResponse('whitelist', 'success');

    Log::spy();

    $action = new UnblockIpActionNormalMode($firewallService, $sshManager);
    $analysisResult = new FirewallAnalysisResult(blocked: true, logs: [], analysis: []);

    $result = $action->handle('192.168.1.1', $host->id, $analysisResult);

    expect($result['success'])->toBeTrue()
        ->and($result)->toHaveKey('message');
});

test('action successfully unblocks IP for DirectAdmin host with BFM operations', function () {
    config()->set('unblock.whitelist_ttl', 86400);

    $host = Host::factory()->create([
        'panel' => PanelType::DIRECTADMIN,
    ]);

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->allows('generateSshKey')->andReturn('/tmp/test_key');
    $sshManager->allows('removeSshKey')->andReturn(true);

    // ✅ Using stub - much cleaner
    $firewallService = FirewallServiceStub::ipNotBlocked()
        ->setCommandResponse('whitelist', 'success')
        ->setCommandResponse('da_bfm_check', '192.168.1.1') // IP found in BFM blacklist
        ->setCommandResponse('da_bfm_remove', 'Removed')
        ->setCommandResponse('da_bfm_whitelist_add', 'Added');

    Log::spy();

    $action = new UnblockIpActionNormalMode($firewallService, $sshManager);
    $analysisResult = new FirewallAnalysisResult(blocked: true, logs: [], analysis: []);

    $result = $action->handle('192.168.1.1', $host->id, $analysisResult);

    expect($result['success'])->toBeTrue();

    // Verify BFM whitelist entry was created
    $entry = BfmWhitelistEntry::where('ip_address', '192.168.1.1')->first();
    expect($entry)->not->toBeNull()
        ->and($entry->host_id)->toBe($host->id)
        ->and($entry->notes)->toContain('UnblockIpActionNormalMode');
});

test('action skips BFM remove when IP not in blacklist', function () {
    config()->set('unblock.whitelist_ttl', 86400);

    $host = Host::factory()->create([
        'panel' => PanelType::DIRECTADMIN,
    ]);

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->allows('generateSshKey')->andReturn('/tmp/test_key');
    $sshManager->allows('removeSshKey')->andReturn(true);

    // ✅ Using stub - IP not in BFM blacklist
    $firewallService = FirewallServiceStub::ipNotBlocked()
        ->setCommandResponse('whitelist', 'success')
        ->setCommandResponse('da_bfm_check', '') // IP NOT found in BFM blacklist
        ->setCommandResponse('da_bfm_whitelist_add', 'Added');

    Log::spy();

    $action = new UnblockIpActionNormalMode($firewallService, $sshManager);
    $analysisResult = new FirewallAnalysisResult(blocked: true, logs: [], analysis: []);

    $result = $action->handle('192.168.1.1', $host->id, $analysisResult);

    expect($result['success'])->toBeTrue();
});

test('action handles BFM failure gracefully', function () {
    config()->set('unblock.whitelist_ttl', 86400);

    $host = Host::factory()->create([
        'panel' => PanelType::DIRECTADMIN,
    ]);

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->allows('generateSshKey')->andReturn('/tmp/test_key');
    $sshManager->allows('removeSshKey')->andReturn(true);

    // ✅ Using stub with exception helper
    $firewallService = FirewallServiceStub::ipNotBlocked()
        ->setCommandResponse('whitelist', 'success')
        ->withExceptionFor('da_bfm_check', new \Exception('BFM check failed'));

    Log::spy();

    $action = new UnblockIpActionNormalMode($firewallService, $sshManager);
    $analysisResult = new FirewallAnalysisResult(blocked: true, logs: [], analysis: []);

    $result = $action->handle('192.168.1.1', $host->id, $analysisResult);

    expect($result['success'])->toBeTrue();
    Log::shouldHaveReceived('warning')->with('Failed to process DirectAdmin BFM (Normal Mode)', Mockery::any());
});

test('action respects custom TTL from config', function () {
    config()->set('unblock.whitelist_ttl', 3600);

    $host = Host::factory()->create([
        'panel' => PanelType::DIRECTADMIN,
    ]);

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->allows('generateSshKey')->andReturn('/tmp/test_key');
    $sshManager->allows('removeSshKey')->andReturn(true);

    // ✅ Using stub
    $firewallService = FirewallServiceStub::ipNotBlocked()
        ->setCommandResponse('whitelist', 'success')
        ->setCommandResponse('da_bfm_check', '192.168.1.1')
        ->setCommandResponse('da_bfm_remove', 'Removed')
        ->setCommandResponse('da_bfm_whitelist_add', 'Added');

    Log::spy();

    $action = new UnblockIpActionNormalMode($firewallService, $sshManager);
    $analysisResult = new FirewallAnalysisResult(blocked: true, logs: [], analysis: []);

    $beforeCreate = now();
    $action->handle('192.168.1.1', $host->id, $analysisResult);
    $afterCreate = now();

    $entry = BfmWhitelistEntry::where('ip_address', '192.168.1.1')->first();
    expect($entry)->not->toBeNull();

    $expectedExpiration = $beforeCreate->addSeconds(3600);
    expect($entry->expires_at)->toBeGreaterThanOrEqual($expectedExpiration->subSeconds(2))
        ->and($entry->expires_at)->toBeLessThanOrEqual($afterCreate->addSeconds(3600)->addSeconds(2));
});

test('action handles host not found gracefully', function () {
    $sshManager = Mockery::mock(SshConnectionManager::class);
    // ✅ Using stub (won't be called since host doesn't exist)
    $firewallService = FirewallServiceStub::ipNotBlocked();

    Log::spy();

    $action = new UnblockIpActionNormalMode($firewallService, $sshManager);
    $analysisResult = new FirewallAnalysisResult(blocked: true, logs: [], analysis: []);

    $result = $action->handle('192.168.1.1', 99999, $analysisResult);

    expect($result['success'])->toBeFalse()
        ->and($result)->toHaveKey('message')
        ->and($result)->toHaveKey('error');

    Log::shouldHaveReceived('error')->with('Unblock process failed (Normal Mode)', Mockery::any());
});

test('action cleans up SSH key even when operation fails', function () {
    $host = Host::factory()->create();

    $keyPath = '/tmp/test_key';
    $cleanupCalled = false;

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->allows('generateSshKey')->andReturn($keyPath);
    $sshManager->allows('removeSshKey')->andReturnUsing(function () use (&$cleanupCalled) {
        $cleanupCalled = true;

        return true;
    });

    // ✅ Using stub with exception helper
    $firewallService = FirewallServiceStub::ipNotBlocked()
        ->withExceptionFor('csf_deny_check', new \Exception('Firewall command failed'));

    Log::spy();

    $action = new UnblockIpActionNormalMode($firewallService, $sshManager);
    $analysisResult = new FirewallAnalysisResult(blocked: true, logs: [], analysis: []);

    $result = $action->handle('192.168.1.1', $host->id, $analysisResult);

    expect($result['success'])->toBeFalse()
        ->and($cleanupCalled)->toBeTrue();
});

test('action logs warning when SSH key cleanup fails', function () {
    $host = Host::factory()->create();

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->allows('generateSshKey')->andReturn('/tmp/key');
    $sshManager->allows('removeSshKey')->andThrow(new \Exception('Failed to remove key'));

    // ✅ Using stub
    $firewallService = FirewallServiceStub::ipNotBlocked()
        ->setCommandResponse('whitelist', 'success');

    Log::spy();

    $action = new UnblockIpActionNormalMode($firewallService, $sshManager);
    $analysisResult = new FirewallAnalysisResult(blocked: true, logs: [], analysis: []);

    $result = $action->handle('192.168.1.1', $host->id, $analysisResult);

    expect($result['success'])->toBeTrue();
    Log::shouldHaveReceived('warning')->with('Failed to cleanup SSH key (Normal Mode)', Mockery::any());
});

test('action logs key operations during execution', function () {
    $host = Host::factory()->create([
        'panel' => PanelType::CPANEL,
    ]);

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->allows('generateSshKey')->andReturn('/tmp/key');
    $sshManager->allows('removeSshKey')->andReturn(true);

    // ✅ Using stub
    $firewallService = FirewallServiceStub::ipNotBlocked()
        ->setCommandResponse('whitelist', 'success');

    Log::spy();

    $action = new UnblockIpActionNormalMode($firewallService, $sshManager);
    $analysisResult = new FirewallAnalysisResult(blocked: true, logs: [], analysis: []);

    $action->handle('192.168.1.1', $host->id, $analysisResult);

    Log::shouldHaveReceived('info')->with('Starting unblock process (Normal Mode)', Mockery::any());
    Log::shouldHaveReceived('debug')->with('Checking if IP is in permanent deny list', Mockery::any());
    Log::shouldHaveReceived('debug')->with('Checking if IP is in temporary deny list', Mockery::any());
    Log::shouldHaveReceived('info')->with('Adding IP to CSF temporary whitelist', Mockery::any());
    Log::shouldHaveReceived('info')->with('Unblock process completed successfully (Normal Mode)', Mockery::any());
});
