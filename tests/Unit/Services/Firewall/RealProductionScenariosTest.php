<?php

declare(strict_types=1);

use App\Jobs\SendReportNotificationJob;
use App\Models\{Host, User};
use App\Services\Firewall\DirectAdminFirewallAnalyzer;
use App\Services\{FirewallService, ReportGenerator};

/**
 * Tests for Real Production Scenarios
 *
 * Based on actual production cases and the work done to fix the main bug:
 * - IP 195.133.213.117 on srv120.tamainut.net
 * - Detection via csf -g with chain_DENY and csf.deny patterns
 * - Single SSH connection per service (no proc_open needed)
 * - Correct report generation and email flow
 */
describe('Real Production Scenarios', function () {

    beforeEach(function () {
        $this->host = Host::factory()->create([
            'panel' => 'directadmin',
            'fqdn' => 'srv120.tamainut.net',
            'port_ssh' => 2244,
        ]);

        $this->user = User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        $this->firewallService = mock(FirewallService::class);
        $this->analyzer = new DirectAdminFirewallAnalyzer($this->firewallService, $this->host);
    });

    test('PRODUCTION CASE: IP blocked with chain_DENY and csf.deny patterns', function () {
        // Real production output from srv120.tamainut.net for IP 195.133.213.117
        $realProductionOutput = <<<'EOD'
Table  Chain            num   pkts bytes target     prot opt in     out     source               destination

No matches found for 195.133.213.117 in iptables


IPSET: Set:chain_DENY Match:195.133.213.117 Setting: File:/etc/csf/csf.deny


ip6tables:

Table  Chain            num   pkts bytes target     prot opt in     out     source               destination

No matches found for 195.133.213.117 in ip6tables

csf.deny: 195.133.213.117 # BFM: Manually 195.133.213.117 (-)
EOD;

        // Mock firewall service responses
        $this->firewallService
            ->shouldReceive('checkProblems')
            ->withArgs(function ($host, $key, $service, $ip) {
                return $service === 'csf' && $ip === '195.133.213.117';
            })
            ->andReturn($realProductionOutput);

        // Mock other service calls to return empty
        $this->firewallService
            ->shouldReceive('checkProblems')
            ->withArgs(function ($host, $key, $service, $ip) {
                return $service !== 'csf';
            })
            ->andReturn('');

        // Mock unblock and whitelist operations
        $this->firewallService
            ->shouldReceive('checkProblems')
            ->withArgs(function ($host, $key, $service, $ip) {
                return in_array($service, ['unblock', 'whitelist']);
            })
            ->andReturn('Operation completed');

        // Execute analysis
        $result = $this->analyzer->analyze('195.133.213.117', 'test_key');

        // Verify detection
        expect($result->isBlocked())->toBeTrue('Must detect IP blocked from chain_DENY and csf.deny patterns');
        expect($result->getAnalysis()['block_sources'])->toContain('csf_primary');
        expect($result->getLogs()['csf'])->toContain('chain_DENY');
        expect($result->getLogs()['csf'])->toContain('csf.deny:');
    });

    test('PRODUCTION CASE: IP in CSF temporary blocks', function () {
        // Production output with temporary blocks
        $tempBlockOutput = <<<'EOD'
Table  Chain            num   pkts bytes target     prot opt in     out     source               destination

filter DENYIN           189      0     0 DROP       all  --  !lo    *       2.2.2.2              0.0.0.0/0

IPSET: No matches found for 2.2.2.2

Temporary Blocks: IP:2.2.2.2 Port: Dir:in TTL:3600 (Manually added: 2.2.2.2 (FR/France/-))
EOD;

        $this->firewallService
            ->shouldReceive('checkProblems')
            ->withArgs(function ($host, $key, $service, $ip) {
                return $service === 'csf';
            })
            ->andReturn($tempBlockOutput);

        $this->firewallService
            ->shouldReceive('checkProblems')
            ->withArgs(function ($host, $key, $service, $ip) {
                return $service !== 'csf';
            })
            ->andReturn('');

        // Mock unblock operations
        $this->firewallService
            ->shouldReceive('checkProblems')
            ->withArgs(function ($host, $key, $service, $ip) {
                return in_array($service, ['unblock', 'whitelist']);
            })
            ->andReturn('Operation completed');

        $result = $this->analyzer->analyze('2.2.2.2', 'test_key');

        expect($result->isBlocked())->toBeTrue('Must detect temporary blocks');
        expect($result->getLogs()['csf'])->toContain('Temporary Blocks');
        expect($result->getLogs()['csf'])->toContain('DENYIN');
    });

    test('PRODUCTION CASE: No blocks found scenario', function () {
        // Clean output with no blocks
        $cleanOutput = <<<'EOD'
Table  Chain            num   pkts bytes target     prot opt in     out     source               destination
No matches found for 1.1.1.1 in iptables


ip6tables:

Table  Chain            num   pkts bytes target     prot opt in     out     source               destination
No matches found for 1.1.1.1 in ip6tables

No blocked entries found
EOD;

        $this->firewallService
            ->shouldReceive('checkProblems')
            ->andReturn($cleanOutput, '', '', '', '', '', '');

        $result = $this->analyzer->analyze('1.1.1.1', 'test_key');

        expect($result->isBlocked())->toBeFalse('Clean IP should not be detected as blocked');
        expect($result->getAnalysis()['block_sources'])->toBeEmpty();
    });

    test('PRODUCTION CASE: Complete report generation and email flow', function () {
        // Use real production output
        $realOutput = <<<'EOD'
IPSET: Set:chain_DENY Match:195.133.213.117 Setting: File:/etc/csf/csf.deny
csf.deny: 195.133.213.117 # BFM: Manually 195.133.213.117 (-)
EOD;

        $this->firewallService
            ->shouldReceive('checkProblems')
            ->withArgs(function ($host, $key, $service, $ip) {
                return $service === 'csf';
            })
            ->andReturn($realOutput);

        $this->firewallService
            ->shouldReceive('checkProblems')
            ->withArgs(function ($host, $key, $service, $ip) {
                return ! in_array($service, ['csf', 'unblock', 'whitelist']);
            })
            ->andReturn('');

        // Mock unblock operations
        $this->firewallService
            ->shouldReceive('checkProblems')
            ->withArgs(function ($host, $key, $service, $ip) {
                return in_array($service, ['unblock', 'whitelist']);
            })
            ->andReturn('Success');

        // Execute analysis
        $analysisResult = $this->analyzer->analyze('195.133.213.117', 'test_key');

        // Generate report
        $reportGen = new ReportGenerator;
        $report = $reportGen->generateReport(
            '195.133.213.117',
            $this->user,
            $this->host,
            $analysisResult
        );

        // Verify report structure
        expect($report->analysis['was_blocked'])->toBeTrue('Report should show IP was blocked');
        expect($report->analysis['block_sources'])->toContain('csf_primary');
        expect($report->logs['csf']['content'])->toContain('chain_DENY');

        // Test email determination
        $job = new SendReportNotificationJob($report->id);
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('determineIfWasBlocked');

        $wasBlocked = $method->invoke($job, $report);
        expect($wasBlocked)->toBeTrue('Email should show IP was unblocked');
    });

    test('PRODUCTION CASE: DirectAdmin BFM blacklist detection', function () {
        // CSF shows no blocks
        $csfClean = 'No matches found for 10.0.0.1 in iptables';

        // But BFM has the IP
        $bfmOutput = '10.0.0.1 20241201103335';

        $this->firewallService
            ->shouldReceive('checkProblems')
            ->withArgs(function ($host, $key, $service, $ip) {
                return $service === 'csf';
            })
            ->andReturn($csfClean);

        $this->firewallService
            ->shouldReceive('checkProblems')
            ->withArgs(function ($host, $key, $service, $ip) {
                return $service === 'da_bfm_check';
            })
            ->andReturn($bfmOutput);

        $this->firewallService
            ->shouldReceive('checkProblems')
            ->withArgs(function ($host, $key, $service, $ip) {
                return ! in_array($service, ['csf', 'da_bfm_check']);
            })
            ->andReturn('');

        // Mock BFM removal
        $this->firewallService
            ->shouldReceive('checkProblems')
            ->withArgs(function ($host, $key, $service, $ip) {
                return $service === 'da_bfm_remove';
            })
            ->andReturn('');

        $result = $this->analyzer->analyze('10.0.0.1', 'test_key');

        expect($result->isBlocked())->toBeTrue('Must detect BFM blacklist');
        expect($result->getAnalysis()['block_sources'])->toContain('da_bfm');
        expect($result->getLogs()['da_bfm'])->toContain('10.0.0.1');
    });

    test('PRODUCTION CASE: IP whitelisted - no operations needed', function () {
        // IP is whitelisted, appears in allow but also in deny temporarily
        $allowOutput = <<<'EOD'
Table  Chain            num   pkts bytes target     prot opt in     out     source               destination
filter ALLOWIN          45       0     0 ACCEPT     all  --  !lo    *       192.168.1.100        0.0.0.0/0

csf.allow: 192.168.1.100 # Whitelisted IP
EOD;

        $this->firewallService
            ->shouldReceive('checkProblems')
            ->withArgs(function ($host, $key, $service, $ip) {
                return $service === 'csf';
            })
            ->andReturn($allowOutput);

        $this->firewallService
            ->shouldReceive('checkProblems')
            ->withArgs(function ($host, $key, $service, $ip) {
                return $service !== 'csf';
            })
            ->andReturn('');

        $result = $this->analyzer->analyze('192.168.1.100', 'test_key');

        // Whitelisted IPs should show as not blocked
        expect($result->isBlocked())->toBeFalse('Whitelisted IP should not trigger unblock operations');
    });

});
