<?php

declare(strict_types=1);

use App\Models\Host;
use App\Services\FirewallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\FirewallTestConstants as TC;

uses(RefreshDatabase::class);

// ============================================================================
// SCENARIO 1: Missing Host ID
// ============================================================================

test('command fails when host ID is not provided', function () {
    $this->artisan('unblock:ip', ['ip' => '192.168.1.1'])
        ->expectsOutput('Host ID is required. Use --host=ID option')
        ->expectsOutput('Example: php artisan unblock:ip 192.168.1.1 --host=1')
        ->assertFailed();
});

// ============================================================================
// SCENARIO 2: Invalid Host ID
// ============================================================================

test('command fails when host does not exist', function () {
    // Bind mocked FirewallService
    app()->bind(FirewallService::class, function () {
        return Mockery::mock(FirewallService::class);
    });

    $this->artisan('unblock:ip', [
        'ip' => '192.168.1.1',
        '--host' => '999', // Non-existent host
    ])
        ->expectsOutputToContain('Failed to unblock IP 192.168.1.1')
        ->assertFailed();
});

// ============================================================================
// SCENARIO 3: Successful Unblock
// ============================================================================

test('command successfully unblocks IP', function () {
    $host = Host::factory()->create([
        'hash' => TC::TEST_SSH_KEY,
        'fqdn' => TC::TEST_HOST_FQDN,
        'ip' => TC::TEST_HOST_IP,
        'port_ssh' => TC::TEST_SSH_PORT,
        'admin' => TC::TEST_ADMIN_USER,
        'panel' => 'cpanel',
    ]);

    // Mock FirewallService
    $firewallService = Mockery::mock(FirewallService::class);
    // Mock deny checks (both return empty - IP not blocked)
    $firewallService->allows('checkProblems')
        ->with(Mockery::type(Host::class), 'default', 'csf_deny_check', '192.168.1.100')
        ->andReturn(''); // Not in permanent deny

    $firewallService->allows('checkProblems')
        ->with(Mockery::type(Host::class), 'default', 'csf_tempip_check', '192.168.1.100')
        ->andReturn(''); // Not in temporary deny

    $firewallService->allows('checkProblems')
        ->with(Mockery::type(Host::class), 'default', 'whitelist_simple', '192.168.1.100')
        ->andReturn('success');

    app()->instance(FirewallService::class, $firewallService);

    $this->artisan('unblock:ip', [
        'ip' => '192.168.1.100',
        '--host' => (string) $host->id,
    ])
        ->expectsOutput("IP 192.168.1.100 has been successfully unblocked from host {$host->id}")
        ->assertSuccessful();
});

// ============================================================================
// SCENARIO 4: Failed Unblock
// ============================================================================

test('command handles unblock failure', function () {
    $host = Host::factory()->create([
        'hash' => TC::TEST_SSH_KEY,
        'fqdn' => TC::TEST_HOST_FQDN,
        'ip' => TC::TEST_HOST_IP,
        'port_ssh' => TC::TEST_SSH_PORT,
        'admin' => TC::TEST_ADMIN_USER,
        'panel' => 'cpanel',
    ]);

    // Mock FirewallService to throw exception
    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('checkProblems')
        ->andThrow(new \App\Exceptions\ConnectionFailedException(
            'Connection failed',
            TC::TEST_HOST_FQDN,
            TC::TEST_SSH_PORT
        ));

    app()->instance(FirewallService::class, $firewallService);

    $this->artisan('unblock:ip', [
        'ip' => '192.168.1.100',
        '--host' => (string) $host->id,
    ])
        ->expectsOutputToContain('Failed to unblock IP 192.168.1.100')
        ->assertFailed();
});

// ============================================================================
// SCENARIO 5: IPv4 Addresses
// ============================================================================

test('command handles standard IPv4 address', function () {
    $host = Host::factory()->create([
        'hash' => TC::TEST_SSH_KEY,
        'fqdn' => TC::TEST_HOST_FQDN,
        'ip' => TC::TEST_HOST_IP,
        'port_ssh' => TC::TEST_SSH_PORT,
        'admin' => TC::TEST_ADMIN_USER,
        'panel' => 'cpanel',
    ]);

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('checkProblems')->andReturn('success');

    app()->instance(FirewallService::class, $firewallService);

    $this->artisan('unblock:ip', [
        'ip' => '203.0.113.42',
        '--host' => (string) $host->id,
    ])
        ->expectsOutputToContain('successfully unblocked')
        ->assertSuccessful();
});

test('command handles private IPv4 address', function () {
    $host = Host::factory()->create([
        'hash' => TC::TEST_SSH_KEY,
        'fqdn' => TC::TEST_HOST_FQDN,
        'ip' => TC::TEST_HOST_IP,
        'port_ssh' => TC::TEST_SSH_PORT,
        'admin' => TC::TEST_ADMIN_USER,
        'panel' => 'cpanel',
    ]);

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('checkProblems')->andReturn('success');

    app()->instance(FirewallService::class, $firewallService);

    $this->artisan('unblock:ip', [
        'ip' => '10.0.0.50',
        '--host' => (string) $host->id,
    ])
        ->assertSuccessful();
});

// ============================================================================
// SCENARIO 6: IPv6 Addresses
// ============================================================================

test('command handles IPv6 address', function () {
    $host = Host::factory()->create([
        'hash' => TC::TEST_SSH_KEY,
        'fqdn' => TC::TEST_HOST_FQDN,
        'ip' => TC::TEST_HOST_IP,
        'port_ssh' => TC::TEST_SSH_PORT,
        'admin' => TC::TEST_ADMIN_USER,
        'panel' => 'cpanel',
    ]);

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('checkProblems')->andReturn('success');

    app()->instance(FirewallService::class, $firewallService);

    $this->artisan('unblock:ip', [
        'ip' => '2001:0db8:85a3::8a2e:0370:7334',
        '--host' => (string) $host->id,
    ])
        ->expectsOutputToContain('successfully unblocked')
        ->assertSuccessful();
});

test('command handles compressed IPv6 address', function () {
    $host = Host::factory()->create([
        'hash' => TC::TEST_SSH_KEY,
        'fqdn' => TC::TEST_HOST_FQDN,
        'ip' => TC::TEST_HOST_IP,
        'port_ssh' => TC::TEST_SSH_PORT,
        'admin' => TC::TEST_ADMIN_USER,
        'panel' => 'cpanel',
    ]);

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('checkProblems')->andReturn('success');

    app()->instance(FirewallService::class, $firewallService);

    $this->artisan('unblock:ip', [
        'ip' => '2001:db8::1',
        '--host' => (string) $host->id,
    ])
        ->assertSuccessful();
});

// ============================================================================
// SCENARIO 7: Different Host Panels
// ============================================================================

test('command works with cPanel host', function () {
    $host = Host::factory()->create([
        'hash' => TC::TEST_SSH_KEY,
        'panel' => 'cpanel',
    ]);

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('checkProblems')->andReturn('success');

    app()->instance(FirewallService::class, $firewallService);

    $this->artisan('unblock:ip', [
        'ip' => '192.168.1.1',
        '--host' => (string) $host->id,
    ])
        ->assertSuccessful();
});

test('command works with DirectAdmin host', function () {
    $host = Host::factory()->create([
        'hash' => TC::TEST_SSH_KEY,
        'panel' => 'directadmin',
    ]);

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('checkProblems')->andReturn('success');

    app()->instance(FirewallService::class, $firewallService);

    $this->artisan('unblock:ip', [
        'ip' => '192.168.1.1',
        '--host' => (string) $host->id,
    ])
        ->assertSuccessful();
});

// ============================================================================
// SCENARIO 8: Command Signature and Description
// ============================================================================

test('command has correct signature', function () {
    $command = new \App\Console\Commands\UnblockIpCommand;

    expect($command->getName())->toBe('unblock:ip');
});

test('command has correct description', function () {
    $command = new \App\Console\Commands\UnblockIpCommand;

    expect($command->getDescription())->toBe('Unblock an IP address from firewall and clear rate limiting records');
});

test('command requires IP argument', function () {
    // Try to run without IP argument - should fail
    $this->artisan('unblock:ip')
        ->assertFailed();
})->throws(\Symfony\Component\Console\Exception\RuntimeException::class);

// ============================================================================
// SCENARIO 9: Exception Handling
// ============================================================================

test('command handles generic exception', function () {
    $host = Host::factory()->create([
        'hash' => TC::TEST_SSH_KEY,
        'fqdn' => TC::TEST_HOST_FQDN,
    ]);

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('checkProblems')
        ->andThrow(new \Exception('Unexpected error occurred'));

    app()->instance(FirewallService::class, $firewallService);

    $this->artisan('unblock:ip', [
        'ip' => '192.168.1.1',
        '--host' => (string) $host->id,
    ])
        ->expectsOutputToContain('Failed to unblock IP 192.168.1.1')
        ->expectsOutputToContain('Unexpected error occurred')
        ->assertFailed();
});

test('command handles firewall exception', function () {
    $host = Host::factory()->create([
        'hash' => TC::TEST_SSH_KEY,
        'fqdn' => TC::TEST_HOST_FQDN,
    ]);

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('checkProblems')
        ->andThrow(new \App\Exceptions\FirewallException('Firewall operation failed'));

    app()->instance(FirewallService::class, $firewallService);

    $this->artisan('unblock:ip', [
        'ip' => '192.168.1.1',
        '--host' => (string) $host->id,
    ])
        ->expectsOutputToContain('Failed to unblock IP 192.168.1.1')
        ->assertFailed();
});

// ============================================================================
// SCENARIO 10: Edge Cases
// ============================================================================

test('command handles host ID as string', function () {
    $host = Host::factory()->create([
        'hash' => TC::TEST_SSH_KEY,
        'fqdn' => TC::TEST_HOST_FQDN,
    ]);

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('checkProblems')->andReturn('success');

    app()->instance(FirewallService::class, $firewallService);

    // Pass host ID as string (command casts to int)
    $this->artisan('unblock:ip', [
        'ip' => '192.168.1.1',
        '--host' => (string) $host->id,
    ])
        ->assertSuccessful();
});

test('command shows error details when available', function () {
    $host = Host::factory()->create([
        'hash' => TC::TEST_SSH_KEY,
        'fqdn' => TC::TEST_HOST_FQDN,
    ]);

    // Create a failure scenario by making FirewallService throw
    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('checkProblems')
        ->andThrow(new \App\Exceptions\CommandExecutionException(
            'Command execution failed',
            'csf -dr 192.168.1.1',
            'Permission denied'
        ));

    app()->instance(FirewallService::class, $firewallService);

    $this->artisan('unblock:ip', [
        'ip' => '192.168.1.1',
        '--host' => (string) $host->id,
    ])
        ->expectsOutputToContain('Failed to unblock IP 192.168.1.1')
        ->assertFailed();
});
