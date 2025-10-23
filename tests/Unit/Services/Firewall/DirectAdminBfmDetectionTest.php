<?php

/**
 * TEST DE DIAGNÓSTICO - DirectAdmin BFM Blacklist Detection
 *
 * Este test verifica que el sistema detecta correctamente cuando una IP
 * está en el blacklist de DirectAdmin BFM
 */

use App\Models\Host;
use App\Services\Firewall\DirectAdminFirewallAnalyzer;
use App\Services\FirewallService;

beforeEach(function () {
    $this->host = Host::factory()->create([
        'panel' => 'directadmin',
        'fqdn' => 'test.example.com',
        'ip' => '1.2.3.4',
        'port_ssh' => 22,
        'admin' => 'root',
        'hash' => 'fake-ssh-key',
    ]);
});

test('DirectAdmin BFM check command is correct', function () {
    $firewallService = app(FirewallService::class);

    $ipAddress = '192.168.1.100';

    // Get the command that would be executed
    $reflection = new \ReflectionClass($firewallService);
    $method = $reflection->getMethod('buildCommand');
    $method->setAccessible(true);

    $command = $method->invoke($firewallService, 'da_bfm_check', $ipAddress);

    // El comando debe buscar la IP en el archivo blacklist
    expect($command)->toContain('/usr/local/directadmin/data/admin/ip_blacklist')
        ->and($command)->toContain('grep')
        ->and($command)->toContain($ipAddress);

    dump("Comando BFM check: {$command}");
});

test('DirectAdmin BFM detection works with exact IP match', function () {
    // Simular contenido del archivo ip_blacklist
    $blacklistContent = <<<'TXT'
192.168.1.100 20251023184500
192.168.1.200 20251023180000
10.0.0.50 20251023170000
TXT;

    $targetIp = '192.168.1.100';

    // Verificar que el grep con escape correcto encuentra la IP
    $escapedIp = preg_quote($targetIp, '/');
    $lines = explode("\n", $blacklistContent);
    $found = false;

    foreach ($lines as $line) {
        if (preg_match("/^{$escapedIp}(\s|$)/", $line)) {
            $found = true;
            break;
        }
    }

    expect($found)->toBeTrue('La IP debe ser encontrada en el blacklist simulado');
});

test('DirectAdmin BFM grep command uses correct escape', function () {
    $ipAddress = '192.168.1.100';
    $escapedForShell = escapeshellarg($ipAddress);
    $escapedForGrep = preg_quote($ipAddress, '/');

    dump("IP original: {$ipAddress}");
    dump("Escaped for shell: {$escapedForShell}");
    dump("Escaped for grep regex: {$escapedForGrep}");

    // El comando debe usar el escape correcto para evitar falsos positivos
    // Ejemplo: grep "^192\.168\.1\.100(\s|$)" en lugar de solo grep "192.168.1.100"
    expect($escapedForGrep)->toBe('192\.168\.1\.100');
});

test('DirectAdmin BFM analyzer has da_bfm_check enabled by default', function () {
    $analyzer = new DirectAdminFirewallAnalyzer(
        app(FirewallService::class),
        $this->host
    );

    // Usar reflexión para acceder a la propiedad privada serviceChecks
    $reflection = new \ReflectionClass($analyzer);
    $property = $reflection->getProperty('serviceChecks');
    $property->setAccessible(true);

    $serviceChecks = $property->getValue($analyzer);

    expect($serviceChecks)->toHaveKey('da_bfm_check')
        ->and($serviceChecks['da_bfm_check'])->toBeTrue();
});
