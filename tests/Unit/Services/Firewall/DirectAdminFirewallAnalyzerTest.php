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
    $this->analyzer = new DirectAdminFirewallAnalyzer($this->firewallService, $this->host);

    // Create mock session that behaves like real SshSession
    $this->mockSession = mock(SshSession::class);
    $this->mockSession->shouldReceive('getSshKeyPath')
        ->andReturn(TC::SSH_KEY);
    $this->mockSession->shouldReceive('cleanup')
        ->andReturn();

    // More robust configuration for generateSshKey
    $this->firewallService
        ->shouldReceive('generateSshKey')
        ->withAnyArgs()
        ->andReturn(TC::SSH_KEY);
});

test('supports returns true for directadmin panel type', function () {
    expect($this->analyzer->supports('directadmin'))->toBeTrue()
        ->and($this->analyzer->supports('cpanel'))->toBeFalse();
});

test('detects IP blocked in CSF', function () {
    // Load CSF stub
    $stub = require base_path('tests/stubs/directadmin_deny_csf.php');
    $this->csfOutput = $stub['csf'];

    // Configure mock for CSF and related services - allow multiple calls
    $this->firewallService
        ->shouldReceive('checkProblems')
        ->withAnyArgs()
        ->andReturn($this->csfOutput, '', '', '', '', '', '', '', '', ''); // Multiple returns for different calls

    // Configure services and analyze
    $result = $this->analyzer
        ->withServiceChecks([
            'csf' => true,
            'csf_deny_check' => false,
            'csf_tempip_check' => false,
            'da_bfm_check' => true,
            ...array_fill_keys(['csf_specials', 'exim_directadmin', 'dovecot_directadmin', 'mod_security_da'], false),
        ])
        ->analyze(TC::BLOCKED_IP, $this->mockSession);

    // Verify results
    expect($result)
        ->toBeInstanceOf(FirewallAnalysisResult::class)
        ->and($result->isBlocked())->toBeTrue()
        ->and($result->getLogs())->toHaveKey('csf');
});

// Eternal issue - TEST ORIGINAL
it('if there are a ip blocked via chain_DENY firewall analyzer must get ip', function () {
    // Load base stub and then modify for specific test case
    $stub = require base_path('tests/stubs/directadmin_deny_csf.php');
    $baseOutput = $stub['csf'];

    // Modify the stub data post-load for this specific test
    $csfOutput = str_replace('192.0.2.123', '195.133.213.117', $baseOutput);
    // Add filter DENYIN entries for chain_DENY scenario
    $csfOutput = str_replace('Table  Chain', 'Table  Chain            num   pkts bytes target     prot opt in     out     source               destination

filter DENYIN           164      0     0 DROP       tcp  --  !lo    *       195.133.213.117      0.0.0.0/0            tcp dpt:2222
filter DENYIN           165      0     0 DROP       tcp  --  !lo    *       195.133.213.117      0.0.0.0/0            tcp dpt:2096

Table  Chain', $csfOutput);

    // Mock básico sin variables
    $firewallService = mock(FirewallService::class);
    $host = new Host(['panel' => 'directadmin']);

    $firewallService->shouldReceive('checkProblems')
        ->andReturn($csfOutput, '', '', '', '', '', '');

    $analyzer = new DirectAdminFirewallAnalyzer($firewallService, $host);

    // Test con IP exacta del stub - SIN VARIABLES
    $result = $analyzer->analyze('195.133.213.117', 'test_key');

    // La IP debe estar bloqueada porque aparece en filter DENYIN y csf.deny
    expect($result->isBlocked())->toBeTrue();
});

// PRODUCTION ISSUE - Reproducir el output exacto de producción
it('PRODUCTION ISSUE - real output from production server must detect as blocked', function () {
    // Load base stub and then modify for production case
    $stub = require base_path('tests/stubs/directadmin_deny_csf.php');
    $baseOutput = $stub['csf'];

    // Modify the stub data post-load for production scenario
    $realProductionOutput = str_replace('192.0.2.123', '195.133.213.117', $baseOutput);
    $realProductionOutput = str_replace('# BFM: dovecot1=31 (blocked-ip.example.net) - Sun Dec  1 10:33:35 2024', '# BFM: Manually 195.133.213.117 (-)', $realProductionOutput);

    // Mock básico sin variables
    $firewallService = mock(FirewallService::class);
    $host = new Host(['panel' => 'directadmin']);

    $firewallService->shouldReceive('checkProblems')
        ->andReturn($realProductionOutput, '', '', '', '', '', '');

    $analyzer = new DirectAdminFirewallAnalyzer($firewallService, $host);

    // Test con IP exacta de producción - SIN VARIABLES
    $result = $analyzer->analyze('195.133.213.117', 'test_key');

    // La IP DEBE estar bloqueada porque:
    // 1. Aparece en "IPSET: Set:chain_DENY Match:195.133.213.117"
    // 2. Aparece en "csf.deny: 195.133.213.117"
    expect($result->isBlocked())->toBeTrue();
});

test('combines results from multiple services', function () {
    // Load stubs
    $csfStub = require base_path('tests/stubs/directadmin_deny_csf.php');
    $eximStub = require base_path('tests/stubs/directadmin_exim.php');
    $this->csfOutput = $csfStub['csf'];
    $this->eximOutput = $eximStub['exim_directadmin'];

    // Configure mock expectations - simplified to avoid argument matching issues
    $this->firewallService
        ->shouldReceive('checkProblems')
        ->withAnyArgs()
        ->andReturn($this->csfOutput, '', $this->eximOutput, '', '');

    // Configure services and analyze
    $result = $this->analyzer
        ->withServiceChecks([
            'csf' => true,
            'exim_directadmin' => true,
            'da_bfm_check' => true, // Enable BFM check
            ...array_fill_keys(['csf_specials', 'dovecot_directadmin', 'mod_security_da'], false),
        ])
        ->analyze(TC::BLOCKED_IP, $this->mockSession);

    // Verify results
    expect($result)
        ->toBeInstanceOf(FirewallAnalysisResult::class)
        ->and($result->isBlocked())->toBeTrue()
        ->and($result->getLogs())
        ->toHaveKey('csf')
        ->toHaveKey('exim');
});

test('skips log analysis if IP is not blocked', function () {
    // Create CSF output with NO blocks - removing all blocking patterns
    $this->csfOutput = <<<'EOD'
Table  Chain            num   pkts bytes target     prot opt in     out     source               destination
No matches found for 192.0.2.123 in iptables


ip6tables:

Table  Chain            num   pkts bytes target     prot opt in     out     source               destination
No matches found for 192.0.2.123 in ip6tables

No blocked entries found
EOD;

    // Configure mock expectations
    $this->firewallService
        ->shouldReceive('checkProblems')
        ->withAnyArgs()
        ->andReturn($this->csfOutput, '', '', ''); // CSF output with no blocks, empty deny file, empty tempip file, empty BFM

    // Configure services and analyze
    $result = $this->analyzer
        ->withServiceChecks([
            'csf' => true,
            'csf_deny_check' => true,
            'csf_tempip_check' => true,
            'da_bfm_check' => true,
            ...array_fill_keys(['csf_specials', 'exim_directadmin', 'dovecot_directadmin', 'mod_security_da'], false),
        ])
        ->analyze(TC::BLOCKED_IP, $this->mockSession);

    // Verify results - IP should NOT be blocked when CSF shows "No blocks found"
    expect($result)
        ->toBeInstanceOf(FirewallAnalysisResult::class)
        ->and($result->isBlocked())->toBeFalse('IP should not be blocked when CSF shows no blocks')
        ->and($result->getLogs())
        ->toHaveKey('csf')
        ->toHaveKey('csf_deny') // Always present due to 7-key guarantee
        ->toHaveKey('csf_tempip') // Always present due to 7-key guarantee
        ->toHaveKey('da_bfm') // Always present due to 7-key guarantee
        ->toHaveKey('exim') // Always present due to 7-key guarantee
        ->toHaveKey('dovecot') // Always present due to 7-key guarantee
        ->toHaveKey('mod_security'); // Always present due to 7-key guarantee
});

test('detects and removes IP from DirectAdmin BFM blacklist when IP is blocked in CSF', function () {
    // Load stubs
    $csfStub = require base_path('tests/stubs/directadmin_deny_csf.php');
    $bfmStub = require base_path('tests/stubs/directadmin_bfm_blacklist.php');
    $this->csfOutput = $csfStub['csf'];
    $this->bfmOutput = $bfmStub['da_bfm_with_ip'];

    // Configure mock expectations
    $this->firewallService
        ->shouldReceive('checkProblems')
        ->withAnyArgs()
        ->andReturn($this->csfOutput, $this->bfmOutput, '', '', '');

    // Configure services and analyze
    $result = $this->analyzer
        ->withServiceChecks([
            'csf' => true,
            'csf_deny_check' => false,
            'csf_tempip_check' => false,
            'da_bfm_check' => true,
            ...array_fill_keys(['csf_specials', 'exim_directadmin', 'dovecot_directadmin', 'mod_security_da'], false),
        ])
        ->analyze(TC::BLOCKED_IP, $this->mockSession);

    // Verify results
    expect($result)
        ->toBeInstanceOf(FirewallAnalysisResult::class)
        ->and($result->isBlocked())->toBeTrue()
        ->and($result->getLogs())
        ->toHaveKey('csf')
        ->toHaveKey('da_bfm');
});

test('constructor sets default service checks when none provided', function () {
    $host = Host::factory()->create(['panel' => 'directadmin']);
    $firewallService = mock(FirewallService::class);

    $analyzer = new DirectAdminFirewallAnalyzer($firewallService, $host);

    // Verify internal state through public API
    expect($analyzer->supports('directadmin'))->toBeTrue();
});

test('constructor accepts custom service checks', function () {
    $host = Host::factory()->create(['panel' => 'directadmin']);
    $firewallService = mock(FirewallService::class);

    $customChecks = [
        'csf' => true,
        'exim_directadmin' => false,
        'da_bfm_check' => false,
    ];

    $analyzer = new DirectAdminFirewallAnalyzer($firewallService, $host, $customChecks);

    // Configure mock for limited services
    $firewallService->shouldReceive('checkProblems')
        ->andReturn('No blocks found');

    $mockSession = mock(SshSession::class);
    $mockSession->shouldReceive('getSshKeyPath')->andReturn('test_key');

    $result = $analyzer->analyze(TC::TEST_IP, $mockSession);

    expect($result)->toBeInstanceOf(FirewallAnalysisResult::class);
});

test('withServiceChecks returns new instance with updated configuration', function () {
    $host = Host::factory()->create(['panel' => 'directadmin']);
    $firewallService = mock(FirewallService::class);

    $analyzer = new DirectAdminFirewallAnalyzer($firewallService, $host);

    $newChecks = ['csf' => false, 'da_bfm_check' => true];
    $newAnalyzer = $analyzer->withServiceChecks($newChecks);

    // Should be a different instance
    expect($newAnalyzer)->not->toBe($analyzer);
    expect($newAnalyzer)->toBeInstanceOf(DirectAdminFirewallAnalyzer::class);
});

test('unblock calls firewall service with correct parameters', function () {
    $host = Host::factory()->create(['panel' => 'directadmin']);
    $firewallService = mock(FirewallService::class);

    $firewallService->shouldReceive('checkProblems')
        ->with($host, 'test_key', 'unblock', TC::TEST_IP)
        ->once()
        ->andReturn('IP unblocked successfully');

    $analyzer = new DirectAdminFirewallAnalyzer($firewallService, $host);

    // Act
    $analyzer->unblock(TC::TEST_IP, 'test_key');

    // Assertion is implicit in mock expectations
    expect(true)->toBeTrue(); // Test passes if mock expectations are met
});

test('analyze handles SSH session object correctly', function () {
    $host = Host::factory()->create(['panel' => 'directadmin']);
    $firewallService = mock(FirewallService::class);

    $mockSession = mock(SshSession::class);
    $mockSession->shouldReceive('getSshKeyPath')->andReturn('session_key');

    $firewallService->shouldReceive('checkProblems')
        ->with($host, 'session_key', 'csf', TC::TEST_IP)
        ->once()
        ->andReturn('No blocks found');

    // Add required returns for 7-key structure
    $firewallService->shouldReceive('checkProblems')
        ->withAnyArgs()
        ->andReturn(''); // Default empty returns

    $analyzer = new DirectAdminFirewallAnalyzer($firewallService, $host);

    $result = $analyzer->analyze(TC::TEST_IP, $mockSession);

    expect($result)->toBeInstanceOf(FirewallAnalysisResult::class)
        ->and($result->getLogs())->toHaveKey('csf')
        ->and($result->getLogs())->toHaveKey('csf_deny')
        ->and($result->getLogs())->toHaveKey('csf_tempip')
        ->and($result->getLogs())->toHaveKey('da_bfm')
        ->and($result->getLogs())->toHaveKey('exim')
        ->and($result->getLogs())->toHaveKey('dovecot')
        ->and($result->getLogs())->toHaveKey('mod_security');
});

test('analyze handles string SSH key correctly for backward compatibility', function () {
    $host = Host::factory()->create(['panel' => 'directadmin']);
    $firewallService = mock(FirewallService::class);

    $firewallService->shouldReceive('checkProblems')
        ->with($host, 'string_key', 'csf', TC::TEST_IP)
        ->once()
        ->andReturn('No blocks found');

    // Add required returns for 7-key structure
    $firewallService->shouldReceive('checkProblems')
        ->withAnyArgs()
        ->andReturn(''); // Default empty returns

    $analyzer = new DirectAdminFirewallAnalyzer($firewallService, $host);

    $result = $analyzer->analyze(TC::TEST_IP, 'string_key');

    expect($result)->toBeInstanceOf(FirewallAnalysisResult::class)
        ->and($result->getLogs())->toHaveKey('csf');
});

test('analyze guarantees 7-key log structure even when services are disabled', function () {
    $host = Host::factory()->create(['panel' => 'directadmin']);
    $firewallService = mock(FirewallService::class);

    // Disable all services except CSF
    $serviceChecks = [
        'csf' => true,
        'csf_deny_check' => false,
        'csf_tempip_check' => false,
        'csf_specials' => false,
        'exim_directadmin' => false,
        'dovecot_directadmin' => false,
        'mod_security_da' => false,
        'da_bfm_check' => false,
    ];

    $firewallService->shouldReceive('checkProblems')
        ->once()
        ->andReturn('No blocks found');

    $analyzer = new DirectAdminFirewallAnalyzer($firewallService, $host, $serviceChecks);

    $result = $analyzer->analyze(TC::TEST_IP, 'test_key');

    // Must have all 7 keys even if services are disabled
    expect($result->getLogs())
        ->toHaveKey('csf')
        ->toHaveKey('csf_deny')
        ->toHaveKey('csf_tempip')
        ->toHaveKey('da_bfm')
        ->toHaveKey('exim')
        ->toHaveKey('dovecot')
        ->toHaveKey('mod_security');
});

test('supports only returns true for directadmin panel type', function () {
    $host = Host::factory()->create(['panel' => 'directadmin']);
    $firewallService = mock(FirewallService::class);

    $analyzer = new DirectAdminFirewallAnalyzer($firewallService, $host);

    expect($analyzer->supports('directadmin'))->toBeTrue()
        ->and($analyzer->supports('cpanel'))->toBeFalse()
        ->and($analyzer->supports('plesk'))->toBeFalse()
        ->and($analyzer->supports('unknown'))->toBeFalse()
        ->and($analyzer->supports(''))->toBeFalse();
});

// CRITICAL BUG FIX TEST - IP 5.102.173.71 case
test('CRITICAL BUG - detects blocked IP when DENYIN present but IPSET shows no matches', function () {
    // Load the specific stub that reproduces the reported bug
    $stub = require base_path('tests/stubs/directadmin_denyin_with_no_ipset_matches.php');
    $problematicOutput = $stub['csf'];

    $host = Host::factory()->create(['panel' => 'directadmin']);
    $firewallService = mock(FirewallService::class);

    // Mock the CSF check to return the problematic output
    $firewallService->shouldReceive('checkProblems')
        ->with($host, 'test_key', 'csf', '5.102.173.71')
        ->once()
        ->andReturn($problematicOutput);

    // Mock empty returns for other checks to focus on CSF analysis
    $firewallService->shouldReceive('checkProblems')
        ->withAnyArgs()
        ->andReturn('');

    $analyzer = new DirectAdminFirewallAnalyzer($firewallService, $host);

    // This should detect the IP as blocked because of DENYIN rules
    // even though IPSET says "No matches found"
    $result = $analyzer->analyze('5.102.173.71', 'test_key');

    expect($result)->toBeInstanceOf(FirewallAnalysisResult::class)
        ->and($result->isBlocked())->toBeTrue('IP should be detected as blocked due to DENYIN rules even when IPSET shows no matches')
        ->and($result->getLogs())->toHaveKey('csf')
        ->and($result->getLogs()['csf'])->toContain('DENYIN')
        ->and($result->getLogs()['csf'])->toContain('5.102.173.71');
});

it('dovecot auth failed logs without csf bfm blocks should not mark ip as blocked', function () {
    // This test verifies the bug fix for scenarios where:
    // - CSF shows "No matches found"
    // - Dovecot shows "auth failed" logs
    // - System should not incorrectly report "IP desbloqueada correctamente"

    $ipAddress = '192.0.2.100';

    // CSF shows NO blocks (typical production output pattern)
    $csfNoBlocksOutput = "No matches found for {$ipAddress} in iptables

IPSET: No matches found for {$ipAddress}

ip6tables:
No matches found for {$ipAddress} in ip6tables";

    // Dovecot logs - auth failures (but should NOT mark as blocked)
    $dovecotAuthFailedOutput = "Jun 30 06:16:54 testserver dovecot[1169]: pop3-login: Login aborted: Connection closed (auth failed, 1 attempts in 0 secs) (auth_failed): user=<test@example.com>, rip={$ipAddress}, lip=203.0.113.10, session=<test123>";

    // Mock FirewallService using the same pattern as other tests
    $this->firewallService->shouldReceive('checkProblems')
        ->with($this->host, '/tmp/test_key', 'csf', $ipAddress)
        ->once()
        ->andReturn($csfNoBlocksOutput);

    // Deep CSF checks - no blocks
    $this->firewallService->shouldReceive('checkProblems')
        ->with($this->host, '/tmp/test_key', 'csf_deny_check', $ipAddress)
        ->once()
        ->andReturn('');

    $this->firewallService->shouldReceive('checkProblems')
        ->with($this->host, '/tmp/test_key', 'csf_tempip_check', $ipAddress)
        ->once()
        ->andReturn('');

    // BFM check - no blocks
    $this->firewallService->shouldReceive('checkProblems')
        ->with($this->host, '/tmp/test_key', 'da_bfm_check', $ipAddress)
        ->once()
        ->andReturn('');

    // Service logs - Dovecot with auth failures
    $this->firewallService->shouldReceive('checkProblems')
        ->with($this->host, '/tmp/test_key', 'dovecot_directadmin', $ipAddress)
        ->once()
        ->andReturn($dovecotAuthFailedOutput);

    // Other services - no significant output
    $this->firewallService->shouldReceive('checkProblems')
        ->with($this->host, '/tmp/test_key', 'exim_directadmin', $ipAddress)
        ->once()
        ->andReturn('');

    $this->firewallService->shouldReceive('checkProblems')
        ->with($this->host, '/tmp/test_key', 'mod_security_da', $ipAddress)
        ->once()
        ->andReturn('');

    // Create analyzer using existing instance from beforeEach
    $analyzer = new DirectAdminFirewallAnalyzer($this->firewallService, $this->host);

    // Analyze using string session (compatibility mode like other tests)
    $result = $analyzer->analyze($ipAddress, '/tmp/test_key');

    // IP should NOT be marked as blocked
    // Auth failed logs are context only, not evidence of firewall blocks
    expect($result->isBlocked())->toBeFalse('ip with only dovecot auth failed logs should not be marked as blocked')
        ->and($result->getLogs())->toHaveKey('csf')
        ->and($result->getLogs())->toHaveKey('dovecot')
        ->and($result->getLogs()['dovecot'])->toContain('auth failed')
        ->and($result->getLogs()['csf'])->toContain('No matches found');
});
