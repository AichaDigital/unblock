<?php

declare(strict_types=1);

use App\Actions\UnblockIpActionNormalMode;
use App\Enums\PanelType;
use App\Models\{BfmWhitelistEntry, Host};
use App\Services\Firewall\FirewallAnalysisResult;
use App\Services\{FirewallService, SshConnectionManager};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('action successfully unblocks IP for cPanel host', function () {
    config()->set('unblock.whitelist_ttl', 86400);

    $host = Host::factory()->create([
        'panel' => PanelType::CPANEL,
    ]);

    // Simple stub: mock only what's absolutely necessary
    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->allows('generateSshKey')->andReturn('/tmp/test_key');
    $sshManager->allows('removeSshKey')->andReturn(true);

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('checkProblems')->andReturn('success');

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

    // Stub: allow any call and return appropriate values
    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('checkProblems')->andReturnUsing(function ($host, $keyPath, $command, $ip) {
        if ($command === 'da_bfm_check') {
            return $ip; // IP found in BFM blacklist
        }

        return 'success';
    });

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

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('checkProblems')->andReturnUsing(function ($host, $keyPath, $command, $ip) {
        if ($command === 'da_bfm_check') {
            return ''; // IP NOT found in BFM blacklist
        }

        return 'success';
    });

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

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('checkProblems')->andReturnUsing(function ($host, $keyPath, $command, $ip) {
        if ($command === 'da_bfm_check') {
            throw new Exception('BFM check failed');
        }

        return 'success';
    });

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

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('checkProblems')->andReturn('success');

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
    $firewallService = Mockery::mock(FirewallService::class);

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

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('checkProblems')->andThrow(new Exception('Firewall command failed'));

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
    $sshManager->allows('removeSshKey')->andThrow(new Exception('Failed to remove key'));

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('checkProblems')->andReturn('success');

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

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('checkProblems')->andReturn('success');

    Log::spy();

    $action = new UnblockIpActionNormalMode($firewallService, $sshManager);
    $analysisResult = new FirewallAnalysisResult(blocked: true, logs: [], analysis: []);

    $action->handle('192.168.1.1', $host->id, $analysisResult);

    Log::shouldHaveReceived('info')->with('Starting unblock process (Normal Mode)', Mockery::any());
    Log::shouldHaveReceived('info')->with('Executing CSF unblock command', Mockery::any());
    Log::shouldHaveReceived('info')->with('Adding IP to CSF temporary whitelist', Mockery::any());
    Log::shouldHaveReceived('info')->with('Unblock process completed successfully (Normal Mode)', Mockery::any());
});
