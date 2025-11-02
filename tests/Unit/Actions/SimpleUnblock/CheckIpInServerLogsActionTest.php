<?php

declare(strict_types=1);

use App\Actions\SimpleUnblock\CheckIpInServerLogsAction;
use App\Models\Host;
use App\Services\FirewallService;
use Mockery\MockInterface;

beforeEach(function () {
    $this->host = Host::factory()->create([
        'panel' => 'cpanel',
        'hash' => 'test-key',
    ]);
});

test('searches for IP in server logs without filtering by domain', function () {
    /** @var FirewallService&MockInterface */
    $firewallService = mock(FirewallService::class);

    // Should search for IP in Exim logs (NO domain filtering)
    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->with(
            \Mockery::type(Host::class),
            'test-key',
            'exim_cpanel',
            '8.8.8.8'  // SOLO IP
        )
        ->andReturn('Some log entry with 8.8.8.8');

    // Should search for IP in Dovecot logs (NO domain filtering)
    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->with(
            \Mockery::type(Host::class),
            'test-key',
            'dovecot_cpanel',
            '8.8.8.8'  // SOLO IP
        )
        ->andReturn('Some log entry with 8.8.8.8');

    $action = new CheckIpInServerLogsAction($firewallService);

    // Note: domain parameter should be IGNORED internally
    $result = $action->handle($this->host, 'test-key', '8.8.8.8', 'ignoreddomain.com');

    expect($result->foundInLogs)->toBeTrue();
});

test('returns not found when IP is not in any server logs', function () {
    /** @var FirewallService&MockInterface */
    $firewallService = mock(FirewallService::class);

    // Empty responses (IP not found)
    $firewallService->shouldReceive('checkProblems')
        ->with(\Mockery::type(Host::class), 'test-key', 'exim_cpanel', '1.1.1.1')
        ->andReturn('');

    $firewallService->shouldReceive('checkProblems')
        ->with(\Mockery::type(Host::class), 'test-key', 'dovecot_cpanel', '1.1.1.1')
        ->andReturn('');

    $action = new CheckIpInServerLogsAction($firewallService);
    $result = $action->handle($this->host, 'test-key', '1.1.1.1', 'anydomain.com');

    expect($result->foundInLogs)->toBeFalse();
    expect($result->ip)->toBe('1.1.1.1');
});

test('works for DirectAdmin servers', function () {
    $hostDa = Host::factory()->create([
        'panel' => 'directadmin',
        'hash' => 'test-key-da',
    ]);

    /** @var FirewallService&MockInterface */
    $firewallService = mock(FirewallService::class);

    // DirectAdmin uses different log paths
    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->with(\Mockery::type(Host::class), 'test-key-da', 'exim_directadmin', '2.2.2.2')
        ->andReturn('Log entry with 2.2.2.2');

    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->with(\Mockery::type(Host::class), 'test-key-da', 'dovecot_directadmin', '2.2.2.2')
        ->andReturn('');

    $action = new CheckIpInServerLogsAction($firewallService);
    $result = $action->handle($hostDa, 'test-key-da', '2.2.2.2', 'irrelevant.com');

    expect($result->foundInLogs)->toBeTrue();
});

