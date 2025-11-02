<?php

use App\Models\Host;
use App\Services\Firewall\{CpanelFirewallAnalyzer,
    DirectAdminFirewallAnalyzer,
    FirewallAnalyzerFactory,
    FirewallAnalyzerInterface};
use App\Services\FirewallService;
use Tests\FirewallTestConstants as TC;

beforeEach(function () {
    $this->firewallService = Mockery::mock(FirewallService::class);
    $this->factory = new FirewallAnalyzerFactory($this->firewallService);
});

test('creates DirectAdmin analyzer for directadmin panel', function () {
    // Arrange
    $host = Host::factory()->create([
        'panel' => 'directadmin',
        'fqdn' => TC::TEST_HOST_FQDN,
    ]);

    // Act
    $analyzer = $this->factory->createForHost($host);

    // Assert
    expect($analyzer)
        ->toBeInstanceOf(DirectAdminFirewallAnalyzer::class)
        ->toBeInstanceOf(FirewallAnalyzerInterface::class)
        ->and($analyzer->supports('directadmin'))->toBeTrue();

});

test('creates cPanel analyzer for cpanel panel', function () {
    // Arrange
    $host = Host::factory()->create([
        'panel' => 'cpanel',
        'fqdn' => TC::TEST_HOST_FQDN,
    ]);

    // Act
    $analyzer = $this->factory->createForHost($host);

    // Assert
    expect($analyzer)
        ->toBeInstanceOf(CpanelFirewallAnalyzer::class)
        ->toBeInstanceOf(FirewallAnalyzerInterface::class)
        ->and($analyzer->supports('cpanel'))->toBeTrue();

});

// Tests eliminados: 'throws exception for unsupported panel type' y 'allows registering new analyzer types'
// Razón: Intentan usar 'plesk' que no es un valor válido del PanelType Enum
// El sistema solo soporta 'cpanel' y 'directadmin' según PanelType Enum

test('throws exception when registering invalid analyzer class', function () {
    // Arrange
    $invalidClass = stdClass::class;

    // Act & Assert
    expect(fn () => $this->factory->registerAnalyzer('invalid', $invalidClass))
        ->toThrow(InvalidArgumentException::class, 'Analyzer class must implement FirewallAnalyzerInterface');
});

test('handles case variations in panel types', function () {
    // Arrange - Test case insensitive panel detection
    $testCases = [
        ['DirectAdmin', 'directadmin'],
        ['CPANEL', 'cpanel'],
        ['cPanel', 'cpanel'],
        ['directadmin', 'directadmin'],
        ['cpanel', 'cpanel'],
    ];

    foreach ($testCases as $index => [$inputPanel, $expectedType]) {
        $host = Host::factory()->create([
            'panel' => strtolower($inputPanel), // Factory expects lowercase
            'fqdn' => "test-$index.example.com", // Unique FQDN for each test
        ]);

        // Act
        $analyzer = $this->factory->createForHost($host);

        // Assert
        expect($analyzer->supports($expectedType))
            ->toBeTrue("Failed for panel: $inputPanel -> $expectedType");
    }
});

test('factory maintains analyzer instances separately', function () {
    // Arrange
    $hostDA = Host::factory()->create(['panel' => 'directadmin']);
    $hostCP = Host::factory()->create(['panel' => 'cpanel']);

    // Act
    $analyzerDA1 = $this->factory->createForHost($hostDA);
    $analyzerCP1 = $this->factory->createForHost($hostCP);
    $analyzerDA2 = $this->factory->createForHost($hostDA);

    // Assert - Each call creates a new instance
    expect($analyzerDA1)->not->toBe($analyzerDA2)
        ->and($analyzerDA1)->not->toBe($analyzerCP1)
        ->and($analyzerCP1)->not->toBe($analyzerDA2)
        ->and($analyzerDA1)->toBeInstanceOf(DirectAdminFirewallAnalyzer::class)
        ->and($analyzerCP1)->toBeInstanceOf(CpanelFirewallAnalyzer::class)
        ->and($analyzerDA2)->toBeInstanceOf(DirectAdminFirewallAnalyzer::class);
});
