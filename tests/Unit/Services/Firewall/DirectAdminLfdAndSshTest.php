<?php

declare(strict_types=1);

use App\Models\Host;
use App\Services\Firewall\{
    DirectAdminFirewallAnalyzer,
    FirewallAnalysisResult
};
use App\Services\{FirewallService, SshSession};
use Tests\FirewallTestConstants as TC;

beforeEach(function () {
    $this->firewallService = mock(FirewallService::class);
    $this->host = new Host([
        'panel' => 'directadmin',
        'fqdn' => TC::HOSTNAME,
        'ip' => TC::SERVER_IP,
        'port_ssh' => TC::SSH_PORT,
        'admin' => TC::ADMIN_USER,
        'hash' => 'test_hash',
    ]);
});

test('lfd_history is treated as context only and never marks IP as blocked', function () {
    $lfdStub = require base_path('tests/stubs/lfd_history_sample.php');
    $lfdOutput = $lfdStub['lfd_history_with_blocks'];

    // CSF shows no blocks
    $csfNoBlocks = "No matches found for 192.0.2.123 in iptables\n\nIPSET: No matches found for 192.0.2.123";

    // Configure ordered mock returns: csf, csf_deny, csf_tempip, da_bfm, exim, dovecot, modsec, lfd_history
    $this->firewallService
        ->shouldReceive('checkProblems')
        ->withAnyArgs()
        ->andReturn($csfNoBlocks, '', '', '', '', '', '', $lfdOutput);

    $analyzer = new DirectAdminFirewallAnalyzer($this->firewallService, $this->host);
    $result = $analyzer->analyze(TC::BLOCKED_IP, 'test_key');

    // LFD history should NOT cause IP to be marked as blocked
    expect($result->isBlocked())->toBeFalse()
        ->and($result->getLogs())->toHaveKey('lfd_history')
        ->and($result->getLogs()['lfd_history'])->toContain('Blocked in csf');
});

test('lfd_history with empty output stores empty string in logs', function () {
    $csfNoBlocks = "No matches found for 192.0.2.123 in iptables";

    $this->firewallService
        ->shouldReceive('checkProblems')
        ->withAnyArgs()
        ->andReturn($csfNoBlocks, '', '', '', '', '', '', '');

    $analyzer = new DirectAdminFirewallAnalyzer($this->firewallService, $this->host);
    $result = $analyzer->analyze(TC::BLOCKED_IP, 'test_key');

    expect($result->getLogs()['lfd_history'])->toBe('');
});

test('SSH error sentinel is detected and stored in ssh_errors', function () {
    $csfNoBlocks = "No matches found for 192.0.2.123 in iptables";

    // Return SSH error for exim_directadmin check
    $this->firewallService
        ->shouldReceive('checkProblems')
        ->withAnyArgs()
        ->andReturn(
            $csfNoBlocks,       // csf
            '',                 // csf_deny
            '',                 // csf_tempip
            '',                 // da_bfm
            '[SSH_ERROR:exim_directadmin]',  // exim - SSH error
            '',                 // dovecot
            '',                 // modsec
            ''                  // lfd_history
        );

    $analyzer = new DirectAdminFirewallAnalyzer($this->firewallService, $this->host);
    $result = $analyzer->analyze(TC::BLOCKED_IP, 'test_key');

    // SSH error should be captured, not treated as data
    expect($result->getLogs())->toHaveKey('ssh_errors')
        ->and($result->getLogs()['ssh_errors'])->toContain('[SSH_ERROR:exim_directadmin]')
        // Exim key should still exist but be empty (SSH error didn't populate it)
        ->and($result->getLogs()['exim'])->toBe('');
});

test('SSH error in lfd_history is captured in ssh_errors', function () {
    $csfNoBlocks = "No matches found for 192.0.2.123 in iptables";

    $this->firewallService
        ->shouldReceive('checkProblems')
        ->withAnyArgs()
        ->andReturn(
            $csfNoBlocks,       // csf
            '',                 // csf_deny
            '',                 // csf_tempip
            '',                 // da_bfm
            '',                 // exim
            '',                 // dovecot
            '',                 // modsec
            '[SSH_ERROR:lfd_history]'  // lfd_history - SSH error
        );

    $analyzer = new DirectAdminFirewallAnalyzer($this->firewallService, $this->host);
    $result = $analyzer->analyze(TC::BLOCKED_IP, 'test_key');

    expect($result->getLogs())->toHaveKey('ssh_errors')
        ->and($result->getLogs()['ssh_errors'])->toContain('[SSH_ERROR:lfd_history]')
        ->and($result->getLogs()['lfd_history'])->toBe('');
});

test('multiple SSH errors are accumulated', function () {
    $csfNoBlocks = "No matches found for 192.0.2.123 in iptables";

    $this->firewallService
        ->shouldReceive('checkProblems')
        ->withAnyArgs()
        ->andReturn(
            $csfNoBlocks,
            '',
            '',
            '',
            '[SSH_ERROR:exim_directadmin]',
            '[SSH_ERROR:dovecot_directadmin]',
            '',
            '[SSH_ERROR:lfd_history]'
        );

    $analyzer = new DirectAdminFirewallAnalyzer($this->firewallService, $this->host);
    $result = $analyzer->analyze(TC::BLOCKED_IP, 'test_key');

    expect($result->getLogs()['ssh_errors'])->toHaveCount(3)
        ->and($result->getLogs()['ssh_errors'])->toContain('[SSH_ERROR:exim_directadmin]')
        ->and($result->getLogs()['ssh_errors'])->toContain('[SSH_ERROR:dovecot_directadmin]')
        ->and($result->getLogs()['ssh_errors'])->toContain('[SSH_ERROR:lfd_history]');
});

test('no ssh_errors key when all commands succeed', function () {
    $csfNoBlocks = "No matches found for 192.0.2.123 in iptables";

    $this->firewallService
        ->shouldReceive('checkProblems')
        ->withAnyArgs()
        ->andReturn($csfNoBlocks, '', '', '', '', '', '', '');

    $analyzer = new DirectAdminFirewallAnalyzer($this->firewallService, $this->host);
    $result = $analyzer->analyze(TC::BLOCKED_IP, 'test_key');

    expect($result->getLogs())->not->toHaveKey('ssh_errors');
});
