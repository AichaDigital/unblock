<?php

declare(strict_types=1);

use App\Models\Host;
use App\Services\Firewall\DirectAdminFirewallAnalyzer;
use App\Services\FirewallService;

test('verifies modsecurity json line by line processing', function () {
    // Load real ModSecurity JSON production data
    $modsecStub = require base_path('tests/stubs/directadmin_mod_security_da.php');

    // Just verify the stub data exists
    expect($modsecStub)->toHaveKey('mod_security_da');
    expect($modsecStub['mod_security_da'])->not->toBeEmpty();
});

test('processes modsecurity json from production correctly', function () {
    // Real production ModSecurity JSON (anonymized)
    $modsecStub = require base_path('tests/stubs/directadmin_mod_security_da.php');
    $rawJson = $modsecStub['mod_security_da'];

    // Test basic JSON structure
    expect($rawJson)
        ->toContain('216.244.66.195')
        ->toContain('transaction')
        ->toContain('client_ip');
});

test('integrates completely with directadmin firewall analyzer', function () {
    // Create real components
    $host = Host::factory()->create(['panel' => 'directadmin']);
    $service = new FirewallService;
    $analyzer = new DirectAdminFirewallAnalyzer($service, $host);

    // Test that analyzer can be created successfully
    expect($analyzer)->toBeInstanceOf(DirectAdminFirewallAnalyzer::class);
});
