<?php

declare(strict_types=1);

use App\Models\Host;
use App\Services\Firewall\CpanelFirewallAnalyzer;
use App\Services\FirewallService;
use Mockery\MockInterface;
use Tests\FirewallTestConstants as TC;

beforeEach(function () {
    /** @var FirewallService&MockInterface */
    $this->firewallService = mock(FirewallService::class);
    $this->host = new Host([
        'panel' => 'cpanel',
        'fqdn' => TC::TEST_HOST_FQDN,
        'ip' => TC::TEST_HOST_IP,
        'port_ssh' => TC::TEST_SSH_PORT,
        'admin' => TC::TEST_ADMIN_USER,
    ]);
    $this->analyzer = new CpanelFirewallAnalyzer($this->firewallService, $this->host);
});

test('exim and dovecot logs are collected even when IP is not blocked', function () {
    $testIp = '192.0.2.100';
    $csfNoBlocks = "No matches found for {$testIp} in iptables";
    $eximOutput = "2026-02-15 10:00:00 authenticator failed for {$testIp}";
    $dovecotOutput = "Feb 15 10:00:00 server dovecot: auth failed, rip={$testIp}";

    // CSF - no blocks
    $this->firewallService
        ->shouldReceive('checkProblems')
        ->with($this->host, TC::TEST_SSH_KEY, 'csf', $testIp)
        ->once()
        ->andReturn($csfNoBlocks);

    // Exim - should be called ALWAYS (not just when blocked)
    $this->firewallService
        ->shouldReceive('checkProblems')
        ->with($this->host, TC::TEST_SSH_KEY, 'exim_cpanel', $testIp)
        ->once()
        ->andReturn($eximOutput);

    // Dovecot - should be called ALWAYS
    $this->firewallService
        ->shouldReceive('checkProblems')
        ->with($this->host, TC::TEST_SSH_KEY, 'dovecot_cpanel', $testIp)
        ->once()
        ->andReturn($dovecotOutput);

    $analyzer = $this->analyzer->withServiceChecks([
        'csf' => true,
        'csf_specials' => false,
        'exim_cpanel' => true,
        'dovecot_cpanel' => true,
        'lfd_history' => false,
    ]);

    $result = $analyzer->analyze($testIp, TC::TEST_SSH_KEY);

    // IP should not be blocked
    expect($result->isBlocked())->toBeFalse()
        // But context logs should be present
        ->and($result->getLogs())->toHaveKey('exim')
        ->and($result->getLogs()['exim'])->toContain('authenticator failed')
        ->and($result->getLogs())->toHaveKey('dovecot')
        ->and($result->getLogs()['dovecot'])->toContain('auth failed');
});

test('cPanel analyzer guarantees 4-key log structure', function () {
    $testIp = '192.0.2.100';
    $csfNoBlocks = "No matches found for {$testIp} in iptables";

    $this->firewallService
        ->shouldReceive('checkProblems')
        ->with($this->host, TC::TEST_SSH_KEY, 'csf', $testIp)
        ->once()
        ->andReturn($csfNoBlocks);

    // Disable all optional services
    $analyzer = $this->analyzer->withServiceChecks([
        'csf' => true,
        'csf_specials' => false,
        'exim_cpanel' => false,
        'dovecot_cpanel' => false,
        'lfd_history' => false,
    ]);

    $result = $analyzer->analyze($testIp, TC::TEST_SSH_KEY);

    // Must have all 4 keys even with services disabled
    expect($result->getLogs())
        ->toHaveKey('csf')
        ->toHaveKey('exim')
        ->toHaveKey('dovecot')
        ->toHaveKey('lfd_history');
});

test('cPanel lfd_history is collected as context only', function () {
    $testIp = '192.0.2.123';
    $lfdStub = require base_path('tests/stubs/lfd_history_sample.php');
    $lfdOutput = $lfdStub['lfd_history_with_blocks'];
    $csfNoBlocks = "No matches found for {$testIp} in iptables";

    $this->firewallService
        ->shouldReceive('checkProblems')
        ->with($this->host, TC::TEST_SSH_KEY, 'csf', $testIp)
        ->once()
        ->andReturn($csfNoBlocks);

    $this->firewallService
        ->shouldReceive('checkProblems')
        ->with($this->host, TC::TEST_SSH_KEY, 'lfd_history', $testIp)
        ->once()
        ->andReturn($lfdOutput);

    $analyzer = $this->analyzer->withServiceChecks([
        'csf' => true,
        'csf_specials' => false,
        'exim_cpanel' => false,
        'dovecot_cpanel' => false,
        'lfd_history' => true,
    ]);

    $result = $analyzer->analyze($testIp, TC::TEST_SSH_KEY);

    // LFD should not cause blocking detection
    expect($result->isBlocked())->toBeFalse()
        ->and($result->getLogs()['lfd_history'])->toContain('Blocked in csf');
});

test('cPanel SSH errors are captured in ssh_errors key', function () {
    $testIp = '192.0.2.100';
    $csfNoBlocks = "No matches found for {$testIp} in iptables";

    $this->firewallService
        ->shouldReceive('checkProblems')
        ->with($this->host, TC::TEST_SSH_KEY, 'csf', $testIp)
        ->once()
        ->andReturn($csfNoBlocks);

    $this->firewallService
        ->shouldReceive('checkProblems')
        ->with($this->host, TC::TEST_SSH_KEY, 'exim_cpanel', $testIp)
        ->once()
        ->andReturn('[SSH_ERROR:exim_cpanel]');

    $this->firewallService
        ->shouldReceive('checkProblems')
        ->with($this->host, TC::TEST_SSH_KEY, 'dovecot_cpanel', $testIp)
        ->once()
        ->andReturn('');

    $analyzer = $this->analyzer->withServiceChecks([
        'csf' => true,
        'csf_specials' => false,
        'exim_cpanel' => true,
        'dovecot_cpanel' => true,
        'lfd_history' => false,
    ]);

    $result = $analyzer->analyze($testIp, TC::TEST_SSH_KEY);

    expect($result->getLogs())->toHaveKey('ssh_errors')
        ->and($result->getLogs()['ssh_errors'])->toContain('[SSH_ERROR:exim_cpanel]')
        ->and($result->getLogs()['exim'])->toBe('');
});

test('no ssh_errors key when all cPanel commands succeed', function () {
    $testIp = '192.0.2.100';
    $csfNoBlocks = "No matches found for {$testIp} in iptables";

    $this->firewallService
        ->shouldReceive('checkProblems')
        ->with($this->host, TC::TEST_SSH_KEY, 'csf', $testIp)
        ->once()
        ->andReturn($csfNoBlocks);

    $this->firewallService
        ->shouldReceive('checkProblems')
        ->with($this->host, TC::TEST_SSH_KEY, 'exim_cpanel', $testIp)
        ->once()
        ->andReturn('');

    $this->firewallService
        ->shouldReceive('checkProblems')
        ->with($this->host, TC::TEST_SSH_KEY, 'dovecot_cpanel', $testIp)
        ->once()
        ->andReturn('');

    $analyzer = $this->analyzer->withServiceChecks([
        'csf' => true,
        'csf_specials' => false,
        'exim_cpanel' => true,
        'dovecot_cpanel' => true,
        'lfd_history' => false,
    ]);

    $result = $analyzer->analyze($testIp, TC::TEST_SSH_KEY);

    expect($result->getLogs())->not->toHaveKey('ssh_errors');
});
