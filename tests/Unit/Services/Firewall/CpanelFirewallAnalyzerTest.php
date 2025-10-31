<?php

use App\Models\Host;
use App\Services\Firewall\{CpanelFirewallAnalyzer, FirewallAnalysisResult};
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

test('supports returns true for cpanel panel type', function () {
    expect($this->analyzer->supports('cpanel'))->toBeTrue()
        ->and($this->analyzer->supports('directadmin'))->toBeFalse();
});

test('analyze detects IP blocked in CSF', function () {
    // Cargar stub
    $stub = require base_path('tests/stubs/cpanel_deny.php');
    $csfOutput = $stub['cpanel'];

    // Configurar mock para CSF
    $this->firewallService
        ->expects('checkProblems')
        ->with($this->host, TC::TEST_SSH_KEY, 'csf', TC::TEST_BLOCKED_IP)
        ->andReturn($csfOutput);

    // REMOVED: Analyzer no longer auto-unblocks, so we don't expect these calls
    // Auto-unblock was a security issue - unblocking must be done by caller

    // Configurar servicios
    $analyzer = $this->analyzer->withServiceChecks([
        'csf' => true,
        'csf_specials' => false,
        'exim_cpanel' => false,
        'dovecot_cpanel' => false,
    ]);

    // Analizar con string como session (compatibility mode)
    $result = $analyzer->analyze(TC::TEST_BLOCKED_IP, TC::TEST_SSH_KEY);

    // Verificar - analyzer only reports, doesn't unblock
    expect($result)->toBeInstanceOf(FirewallAnalysisResult::class)
        ->and($result->isBlocked())->toBeTrue();
});

test('analyze detects authentication failures in Exim logs', function () {
    // Cargar stubs
    $csfStub = require base_path('tests/stubs/cpanel_deny.php');
    $eximStub = require base_path('tests/stubs/cpanel_exim.php');
    $csfOutput = $csfStub['cpanel'];
    $eximOutput = $eximStub['exim'];

    // Configurar mock - only CSF and Exim, no unblock/whitelist
    $this->firewallService
        ->shouldReceive('checkProblems')
        ->times(2)  // CSF and Exim only (no auto-unblock anymore)
        ->withArgs(function ($host, $key, $service, $ip) {
            return $host === $this->host
                && $key === 'test-key'
                && in_array($service, ['csf', 'exim_cpanel'])
                && $ip === TC::TEST_BLOCKED_IP;
        })
        ->andReturn($csfOutput, $eximOutput);

    // Configurar servicios
    $analyzer = $this->analyzer->withServiceChecks([
        'csf' => true,
        'csf_specials' => false,
        'exim_cpanel' => true,
        'dovecot_cpanel' => false,
    ]);

    // Analizar con string como session (compatibility mode)
    $result = $analyzer->analyze(TC::TEST_BLOCKED_IP, 'test-key');

    // Verificar - analyzer only reports, doesn't unblock
    expect($result->isBlocked())->toBeTrue()
        ->and($result->getLogs())
        ->toHaveKey('csf')
        ->toHaveKey('exim');
});

test('analyze DOES NOT trigger auto-unblock for blocked IPs', function () {
    // Security test: verify analyzer doesn't auto-unblock
    // Unblocking must be done by caller based on complete validation

    $stub = require base_path('tests/stubs/cpanel_deny.php');
    $csfOutput = $stub['cpanel'];

    // Configurar mock - should only call csf, NEVER unblock/whitelist
    $this->firewallService
        ->shouldReceive('checkProblems')
        ->once()
        ->with($this->host, TC::TEST_SSH_KEY, 'csf', TC::TEST_BLOCKED_IP)
        ->andReturn($csfOutput);

    // Should NOT receive calls to unblock or whitelist
    $this->firewallService
        ->shouldNotReceive('checkProblems')
        ->with($this->host, TC::TEST_SSH_KEY, 'unblock', TC::TEST_BLOCKED_IP);

    $this->firewallService
        ->shouldNotReceive('checkProblems')
        ->with($this->host, TC::TEST_SSH_KEY, 'whitelist', TC::TEST_BLOCKED_IP);

    // Configurar servicios
    $analyzer = $this->analyzer->withServiceChecks([
        'csf' => true,
        'csf_specials' => false,
        'exim_cpanel' => false,
        'dovecot_cpanel' => false,
    ]);

    // Analizar - should detect block but NOT unblock
    $result = $analyzer->analyze(TC::TEST_BLOCKED_IP, TC::TEST_SSH_KEY);

    // Verify it detected the block
    expect($result->isBlocked())->toBeTrue();
});

test('dovecot auth failed logs without csf blocks should not mark ip as blocked', function () {
    // This test verifies the bug fix for scenarios where
    // IPs with only Dovecot "auth failed" logs (no actual firewall blocks)
    // were incorrectly reported as "IP desbloqueada correctamente"

    $testIp = '192.0.2.100';

    // CSF shows no blocks (the IP is not actually blocked)
    $csfNoBlocksOutput = "No matches found for {$testIp} in iptables";

    // Dovecot shows auth failed logs (historical failed attempts, not blocks)
    $dovecotAuthFailedOutput = "Jun 30 06:16:54 testserver dovecot[1169]: pop3-login: Login aborted: Connection closed (auth failed, 1 attempts in 0 secs) (auth_failed): user=<test@example.com>, rip={$testIp}";

    // Configure mocks - CSF shows no blocks, Dovecot shows auth failures
    $this->firewallService
        ->shouldReceive('checkProblems')
        ->with($this->host, TC::TEST_SSH_KEY, 'csf', $testIp)
        ->once()
        ->andReturn($csfNoBlocksOutput);

    // Dovecot should NOT be checked because CSF shows no blocks
    // This verifies the "Solo si la IP está bloqueada, procedemos a buscar en los logs" logic

    // Configure services
    $analyzer = $this->analyzer->withServiceChecks([
        'csf' => true,
        'csf_specials' => false,
        'exim_cpanel' => false,
        'dovecot_cpanel' => true, // Enable Dovecot check
    ]);

    // Analyze
    $result = $analyzer->analyze($testIp, TC::TEST_SSH_KEY);

    // IP should not be marked as blocked
    // This prevents false "IP desbloqueada correctamente" reports
    expect($result->isBlocked())->toBeFalse('ip with only dovecot auth failed logs should not be marked as blocked');
});
