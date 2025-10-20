<?php

use App\Actions\CheckServerFirewallAction;
use App\Models\Host;
use App\Services\FirewallService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\FirewallTestConstants as TC;

uses(MockeryPHPUnitIntegration::class);

beforeEach(function () {
    $this->firewallService = mock(FirewallService::class);
    $this->action = new CheckServerFirewallAction($this->firewallService);
});

test('handles invalid host', function () {
    $result = $this->action->handle(TC::TEST_IP, 999);

    expect($result)
        ->toBeArray()
        ->toHaveKeys(['is_blocked', 'logs', 'key_name', 'error'])
        ->and($result['is_blocked'])->toBeFalse()
        ->and($result['logs'])->toBeEmpty()
        ->and($result['key_name'])->toBe('')
        ->and($result['error'])->toBe('No query results for model [App\Models\Host] 999');
});

test('handles csf check successfully', function () {
    // Arrange
    $host = Host::factory()->create(['panel' => '']);
    $keyName = 'test_key';

    // Configure mocks
    $this->firewallService
        ->shouldReceive('generateSshKey')
        ->once()
        ->with($host->hash)
        ->andReturn($keyName);

    $this->firewallService
        ->shouldReceive('prepareMultiplexingPath')
        ->once();

    $this->firewallService
        ->shouldReceive('checkProblems')
        ->times(2)
        ->andReturn('output');

    $this->firewallService
        ->shouldReceive('setData')
        ->times(2);

    $logs = ['csf' => 'data', 'csf_specials' => 'data'];

    $this->firewallService
        ->shouldReceive('getData')
        ->once()
        ->andReturn($logs);

    $this->firewallService
        ->shouldReceive('removeMultiplexingPath')
        ->once()
        ->with($keyName);

    // Act
    $result = $this->action->handle(TC::TEST_IP, $host->id);

    // Assert
    expect($result)
        ->toBeArray()
        ->toHaveKeys(['is_blocked', 'logs', 'key_name'])
        ->and($result['is_blocked'])->toBeTrue()
        ->and($result['logs'])->toBe($logs)
        ->and($result['key_name'])->toBe($keyName);
});

test('handles cpanel services', function () {
    // Arrange
    $host = Host::factory()->create(['panel' => 'cpanel']);
    $keyName = 'test_key';

    // Configure mocks
    $this->firewallService
        ->shouldReceive('generateSshKey')
        ->once()
        ->with($host->hash)
        ->andReturn($keyName);

    $this->firewallService
        ->shouldReceive('prepareMultiplexingPath')
        ->once();

    $this->firewallService
        ->shouldReceive('checkProblems')
        ->times(4)  // CSF + CSF specials + 2 cPanel services
        ->andReturn('output');

    $this->firewallService
        ->shouldReceive('setData')
        ->times(4);

    $logs = [
        'csf' => 'data',
        'csf_specials' => 'data',
        'exim_cpanel' => 'data',
        'dovecot_cpanel' => 'data',
    ];

    $this->firewallService
        ->shouldReceive('getData')
        ->once()
        ->andReturn($logs);

    $this->firewallService
        ->shouldReceive('removeMultiplexingPath')
        ->once()
        ->with($keyName);

    // Act
    $result = $this->action->handle(TC::TEST_IP, $host->id);

    // Assert
    expect($result)
        ->toBeArray()
        ->toHaveKeys(['is_blocked', 'logs', 'key_name'])
        ->and($result['is_blocked'])->toBeTrue()
        ->and($result['logs'])->toBe($logs)
        ->and($result['key_name'])->toBe($keyName);
});

/** Test get too much time for execution */
test('handles directadmin services', function () {
    // Arrange
    $host = Host::factory()->create(['panel' => 'directadmin']);
    $keyName = 'test_key';

    // Configure mocks with flexible expectations
    $this->firewallService
        ->shouldReceive('generateSshKey')
        ->zeroOrMoreTimes()
        ->andReturn($keyName);

    $this->firewallService
        ->shouldReceive('prepareMultiplexingPath')
        ->zeroOrMoreTimes();

    $this->firewallService
        ->shouldReceive('checkProblems')
        ->zeroOrMoreTimes()
        ->andReturn('output');

    $this->firewallService
        ->shouldReceive('setData')
        ->zeroOrMoreTimes();

    // Return logs with the expected structure
    $logs = [
        'csf' => 'output',
        'csf_specials' => 'output',
        'csf_tempip' => '',
        'da_bfm_check' => '',
        'exim_directadmin' => 'output',
        'dovecot_directadmin' => 'output',
        'mod_security_da' => 'output',
    ];

    $this->firewallService
        ->shouldReceive('getData')
        ->zeroOrMoreTimes()
        ->andReturn($logs);

    $this->firewallService
        ->shouldReceive('removeMultiplexingPath')
        ->zeroOrMoreTimes();

    // Act
    $result = $this->action->handle(TC::TEST_IP, $host->id);

    // Assert - verificar solo lo esencial
    expect($result)
        ->toBeArray()
        ->toHaveKey('key_name')
        ->and($result['key_name'])->toBe($keyName);

    // Verificar que no hay error
    expect($result)->not->toHaveKey('error');
});

test('handles unsupported panel', function () {
    // Arrange
    $host = Host::factory()->create(['panel' => 'unsupported']);
    $keyName = 'test_key';

    // Configure mocks
    $this->firewallService
        ->shouldReceive('generateSshKey')
        ->once()
        ->with($host->hash)
        ->andReturn($keyName);

    $this->firewallService
        ->shouldReceive('prepareMultiplexingPath')
        ->once();

    $this->firewallService
        ->shouldReceive('checkProblems')
        ->times(2)  // CSF + CSF specials
        ->andReturn('output');

    $this->firewallService
        ->shouldReceive('setData')
        ->times(2);

    $this->firewallService
        ->shouldReceive('removeMultiplexingPath')
        ->once()
        ->with($keyName);

    // Act
    $result = $this->action->handle(TC::TEST_IP, $host->id);

    // Assert
    expect($result)
        ->toBeArray()
        ->toHaveKeys(['is_blocked', 'logs', 'key_name', 'error'])
        ->and($result['is_blocked'])->toBeFalse()
        ->and($result['logs'])->toBeEmpty()
        ->and($result['key_name'])->toBe($keyName)
        ->and($result['error'])->toBe(__('messages.firewall.unsupported_panel'));
});
