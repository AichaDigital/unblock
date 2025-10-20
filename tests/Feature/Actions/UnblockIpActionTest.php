<?php

declare(strict_types=1);

use App\Actions\UnblockIpAction;
use App\Exceptions\{
    CommandExecutionException,
    ConnectionFailedException,
    FirewallException
};
use App\Models\Host;
use App\Services\FirewallService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\FirewallTestConstants as TC;

uses(MockeryPHPUnitIntegration::class);

beforeEach(function () {
    $this->firewallService = Mockery::mock(FirewallService::class);
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
    ]);

    $this->firewallService
        ->expects('checkProblems')
        ->once()
        ->with(
            \Mockery::on(fn ($arg) => $arg instanceof Host && $arg->id === $host->id),
            TC::TEST_SSH_KEY,
            'unblock',
            TC::TEST_IP
        )
        ->andReturn('success');

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

    $this->firewallService
        ->expects('checkProblems')
        ->once()
        ->with(
            \Mockery::on(fn ($arg) => $arg instanceof Host && $arg->id === $host->id),
            TC::TEST_SSH_KEY,
            'unblock',
            TC::TEST_IP
        )
        ->andThrow(new ConnectionFailedException(
            'Connection failed',
            $host->fqdn,
            3,
            TC::TEST_IP
        ));

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

    $this->firewallService
        ->expects('checkProblems')
        ->once()
        ->with(
            \Mockery::on(fn ($arg) => $arg instanceof Host && $arg->id === $host->id),
            TC::TEST_SSH_KEY,
            'unblock',
            TC::TEST_IP
        )
        ->andThrow(new CommandExecutionException(
            'csf -dr '.TC::TEST_IP,
            'output',
            'error output',
            'Command failed'
        ));

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

    $this->firewallService
        ->expects('checkProblems')
        ->once()
        ->with(
            \Mockery::on(fn ($arg) => $arg instanceof Host && $arg->id === $host->id),
            TC::TEST_SSH_KEY,
            'unblock',
            TC::TEST_IP
        )
        ->andThrow(new FirewallException(
            'Firewall error',
            $host->fqdn,
            TC::TEST_IP
        ));

    // Act
    $result = $this->action->handle(TC::TEST_IP, $host->id, TC::TEST_SSH_KEY);

    // Assert
    expect($result)
        ->toBeArray()
        ->toHaveKey('error')
        ->and($result['success'])->toBeFalse()
        ->and($result['message'])->toBe(__('messages.firewall.unblock_failed'));
});
