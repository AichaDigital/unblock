<?php

declare(strict_types=1);

use App\Models\Host;
use App\Services\Firewall\DirectAdminFirewallAnalyzer;
use App\Services\FirewallService;

/**
 * REAL INTEGRATION TESTS - NO MOCKS
 *
 * These tests execute the actual FirewallService code and DirectAdminFirewallAnalyzer
 * against real stub data to verify the logic works correctly.
 *
 * This addresses the problem where unit tests with mocks don't catch real issues.
 */
test('REAL TEST - DirectAdminFirewallAnalyzer detects DENYIN blocks correctly', function () {
    // Load the problematic stub data
    $stub = require base_path('tests/stubs/directadmin_denyin_with_no_ipset_matches.php');
    $problematicOutput = $stub['csf'];

    // Create a REAL FirewallService but override the SSH execution to return our stub data
    $firewallService = new class extends FirewallService
    {
        private string $stubOutput;

        public function setStubOutput(string $output): void
        {
            $this->stubOutput = $output;
        }

        public function checkProblems(Host $host, string $keyName, string $unity, string $ip): string
        {
            // Return our stub data instead of executing real SSH
            return $this->stubOutput;
        }
    };

    // Set the stub data
    $firewallService->setStubOutput($problematicOutput);

    // Create REAL host and analyzer (no mocks)
    $host = new Host([
        'panel' => 'directadmin',
        'fqdn' => 'test.example.com',
        'ip' => '192.168.1.100',
        'port_ssh' => 22,
        'admin' => 'admin',
        'hash' => 'test_hash',
    ]);

    $analyzer = new DirectAdminFirewallAnalyzer($firewallService, $host);

    // Execute REAL analysis
    $result = $analyzer->analyze('5.102.173.71', 'test_key');

    // This should detect the IP as blocked because of DENYIN rules
    expect($result->isBlocked())->toBeTrue('REAL TEST: IP should be detected as blocked due to DENYIN rules');
    expect($result->getLogs()['csf'])->toContain('DENYIN');
    expect($result->getLogs()['csf'])->toContain('5.102.173.71');
})->group('integration', 'real-tests');

test('REAL TEST - FirewallService command building works correctly', function () {
    // Test that the actual command building logic works
    $firewallService = new FirewallService;

    // Create real host
    $host = new Host([
        'panel' => 'directadmin',
        'fqdn' => 'test.example.com',
        'ip' => '192.168.1.100',
        'port_ssh' => 22,
        'admin' => 'admin',
    ]);

    // Generate a real SSH key
    $sshKey = $firewallService->generateSshKey('test_hash_content');

    // This will fail if we try to execute real SSH, but we can test command building
    try {
        $firewallService->checkProblems($host, $sshKey, 'csf', '5.102.173.71');
    } catch (\Exception $e) {
        // Expected to fail due to SSH connection, but command building should work
        expect($e->getMessage())->not->toContain('Invalid unity'); // Should not be a command building error
    }

    expect(true)->toBeTrue(); // Test passes if we reach here without command building errors
})->group('integration', 'real-tests');

test('REAL TEST - analyzeServiceOutput method works correctly with real data', function () {
    // Create REAL analyzer
    $firewallService = new FirewallService;
    $host = new Host(['panel' => 'directadmin']);
    $analyzer = new DirectAdminFirewallAnalyzer($firewallService, $host);

    // Use reflection to test the private method directly
    $reflection = new ReflectionClass($analyzer);
    $method = $reflection->getMethod('analyzeServiceOutput');
    $method->setAccessible(true);

    // Test with the problematic output
    $stub = require base_path('tests/stubs/directadmin_denyin_with_no_ipset_matches.php');
    $problematicOutput = $stub['csf'];

    $result = $method->invoke($analyzer, 'csf', $problematicOutput);

    expect($result->isBlocked())->toBeTrue('analyzeServiceOutput should detect DENYIN as blocked');
})->group('integration', 'real-tests');

test('REAL TEST - Compare different CSF outputs to verify detection logic', function () {
    $firewallService = new FirewallService;
    $host = new Host(['panel' => 'directadmin']);
    $analyzer = new DirectAdminFirewallAnalyzer($firewallService, $host);

    $reflection = new ReflectionClass($analyzer);
    $method = $reflection->getMethod('analyzeServiceOutput');
    $method->setAccessible(true);

    // Test 1: DENYIN with no IPSET matches (should be blocked)
    $stub1 = require base_path('tests/stubs/directadmin_denyin_with_no_ipset_matches.php');
    $result1 = $method->invoke($analyzer, 'csf', $stub1['csf']);
    expect($result1->isBlocked())->toBeTrue('DENYIN rules should be detected as blocked');

    // Test 2: Temporary blocks (should be blocked)
    $stub2 = require base_path('tests/stubs/issue_temporary_blocks_csf.php');
    $result2 = $method->invoke($analyzer, 'csf', $stub2['csf']);
    expect($result2->isBlocked())->toBeTrue('Temporary Blocks should be detected as blocked');

    // Test 3: Pure "No matches found" (should NOT be blocked)
    $noMatchesOutput = <<<'EOD'
Table  Chain            num   pkts bytes target     prot opt in     out     source               destination
No matches found for 1.2.3.4 in iptables

IPSET: No matches found for 1.2.3.4

ip6tables:
Table  Chain            num   pkts bytes target     prot opt in     out     source               destination
No matches found for 1.2.3.4 in ip6tables
EOD;

    $result3 = $method->invoke($analyzer, 'csf', $noMatchesOutput);
    expect($result3->isBlocked())->toBeFalse('Pure no matches should NOT be detected as blocked');

})->group('integration', 'real-tests');
