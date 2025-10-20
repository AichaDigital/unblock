<?php

declare(strict_types=1);

use App\Models\Host;
use App\Services\Firewall\DirectAdminFirewallAnalyzer;
use App\Services\FirewallService;

test('DEBUG - Analizar paso a paso el flujo del problema', function () {
    // Load the correct stub data that actually has a blocked IP
    $stub = require base_path('tests/stubs/directadmin_deny_csf.php');

    // Create required dependencies with stub data
    $firewallService = new class extends FirewallService
    {
        private array $stubData;

        public function __construct()
        {
            $stub = require base_path('tests/stubs/directadmin_deny_csf.php');
            $this->stubData = $stub;
        }

        public function checkProblems($host, string $sshKey, string $unity, string $ip = ''): string
        {
            return $this->stubData[$unity] ?? '';
        }
    };

    $host = Host::factory()->create([
        'panel' => 'directadmin',
        'fqdn' => 'test.example.com',
        'ip' => '192.168.1.100',
    ]);

    // Test analyzer directly
    $analyzer = new DirectAdminFirewallAnalyzer($firewallService, $host);
    $analysisResult = $analyzer->analyze('5.102.173.71', 'test-key');

    // This IP should be blocked according to the stub data
    expect($analysisResult->isBlocked())->toBeTrue();
    expect($analysisResult->getLogs())->toHaveKey('csf');
    expect($analysisResult->getLogs())->toHaveKey('csf_deny');
    expect($analysisResult->getLogs())->toHaveKey('csf_tempip');
    expect($analysisResult->getLogs())->toHaveKey('da_bfm');
    expect($analysisResult->getLogs())->toHaveKey('exim');
    expect($analysisResult->getLogs())->toHaveKey('dovecot');
    expect($analysisResult->getLogs())->toHaveKey('mod_security');
});
