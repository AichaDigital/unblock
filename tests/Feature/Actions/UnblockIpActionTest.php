<?php

declare(strict_types=1);

use App\Actions\UnblockIpAction;
use App\Exceptions\{
    CommandExecutionException,
    ConnectionFailedException,
    FirewallException
};
use App\Models\Host;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\FirewallTestConstants as TC;
use Tests\Helpers\FirewallServiceStub;

uses(MockeryPHPUnitIntegration::class);

beforeEach(function () {
    // Use stub by default - only use mocks for error cases
    $this->firewallService = FirewallServiceStub::ipNotBlocked();
    $this->action = new UnblockIpAction($this->firewallService);
});

test('handles invalid host', function () {
    // Act
    $result = $this->action->handle(TC::TEST_IP, 999, TC::TEST_SSH_KEY);

    // Assert
    expect($result)
        ->toBeArray()
        ->toHaveKey('error')
        ->and($result['success'])->toBeFalse()
        ->and($result['message'])->toBe(__('messages.firewall.unblock_failed'));
});

test('unblocks ip successfully', function () {
    // Arrange
    $host = Host::factory()->create([
        'hash' => TC::TEST_SSH_KEY,
        'fqdn' => TC::TEST_HOST_FQDN,
        'ip' => TC::TEST_HOST_IP,
        'port_ssh' => TC::TEST_SSH_PORT,
        'admin' => TC::TEST_ADMIN_USER,
        'panel' => 'cpanel',
    ]);

    // ✅ Using stub instead of mocks - cleaner and uses real FirewallService logic
    $this->firewallService = FirewallServiceStub::ipNotBlocked()
        ->setCommandResponse('whitelist_simple', 'success');

    $this->action = new UnblockIpAction($this->firewallService);

    // Act
    $result = $this->action->handle(TC::TEST_IP, $host->id, TC::TEST_SSH_KEY);

    // Assert
    expect($result)
        ->toBeArray()
        ->and($result['success'])->toBeTrue()
        ->and($result['message'])->toBe(__('messages.firewall.ip_unblocked'));
});

test('handles connection failure', function () {
    // Arrange
    $host = Host::factory()->create([
        'hash' => TC::TEST_SSH_KEY,
        'fqdn' => TC::TEST_HOST_FQDN,
        'ip' => TC::TEST_HOST_IP,
        'port_ssh' => TC::TEST_SSH_PORT,
        'admin' => TC::TEST_ADMIN_USER,
    ]);

    // ✅ Using stub with exception helper for error testing
    $this->firewallService = FirewallServiceStub::ipNotBlocked()
        ->withExceptionFor('csf_deny_check', new ConnectionFailedException(
            'Connection failed',
            $host->fqdn,
            3,
            TC::TEST_IP
        ));

    $this->action = new UnblockIpAction($this->firewallService);

    // Act
    $result = $this->action->handle(TC::TEST_IP, $host->id, TC::TEST_SSH_KEY);

    // Assert
    expect($result)
        ->toBeArray()
        ->toHaveKey('error')
        ->and($result['success'])->toBeFalse()
        ->and($result['message'])->toBe(__('messages.firewall.unblock_failed'));
});

test('handles command execution failure', function () {
    // Arrange
    $host = Host::factory()->create([
        'hash' => TC::TEST_SSH_KEY,
        'fqdn' => TC::TEST_HOST_FQDN,
        'ip' => TC::TEST_HOST_IP,
        'port_ssh' => TC::TEST_SSH_PORT,
        'admin' => TC::TEST_ADMIN_USER,
    ]);

    // ✅ Using stub with exception helper
    $this->firewallService = FirewallServiceStub::ipNotBlocked()
        ->withExceptionFor('csf_deny_check', new CommandExecutionException(
            'csf -dr '.TC::TEST_IP,
            'output',
            'error output',
            'Command failed'
        ));

    $this->action = new UnblockIpAction($this->firewallService);

    // Act
    $result = $this->action->handle(TC::TEST_IP, $host->id, TC::TEST_SSH_KEY);

    // Assert
    expect($result)
        ->toBeArray()
        ->toHaveKey('error')
        ->and($result['success'])->toBeFalse()
        ->and($result['message'])->toBe(__('messages.firewall.unblock_failed'));
});

test('handles firewall exception', function () {
    // Arrange
    $host = Host::factory()->create([
        'hash' => TC::TEST_SSH_KEY,
        'fqdn' => TC::TEST_HOST_FQDN,
        'ip' => TC::TEST_HOST_IP,
        'port_ssh' => TC::TEST_SSH_PORT,
        'admin' => TC::TEST_ADMIN_USER,
    ]);

    // ✅ Using stub with exception helper
    $this->firewallService = FirewallServiceStub::ipNotBlocked()
        ->withExceptionFor('csf_deny_check', new FirewallException(
            'Firewall error',
            $host->fqdn,
            TC::TEST_IP
        ));

    $this->action = new UnblockIpAction($this->firewallService);

    // Act
    $result = $this->action->handle(TC::TEST_IP, $host->id, TC::TEST_SSH_KEY);

    // Assert
    expect($result)
        ->toBeArray()
        ->toHaveKey('error')
        ->and($result['success'])->toBeFalse()
        ->and($result['message'])->toBe(__('messages.firewall.unblock_failed'));
});
