<?php

declare(strict_types=1);

use App\Services\FirewallService;

/**
 * TEST CRÍTICO: Filtrado de IP específica en ModSecurity
 *
 * Este test verifica que solo se procesan logs de la IP solicitada
 * y se ignoran logs de otras IPs diferentes.
 */
test('filters modsecurity logs by specific ip only', function () {
    // Arrange: JSON con diferentes IPs mezcladas (estructura REAL: messages[] en nivel raíz)
    $mixedIpJson = '{"transaction":{"client_ip":"22.2.2.2","time_stamp":"Thu Jun 26 05:31:50 2025","request":{"uri":"/test1"}},"messages":[{"message":"Test blocked IP 22.2.2.2","details":{"ruleId":"1001"}}]}
{"transaction":{"client_ip":"111.68.6.186","time_stamp":"Thu Jun 26 05:32:00 2025","request":{"uri":"/test2"}},"messages":[{"message":"Test blocked IP 111.68.6.186","details":{"ruleId":"1002"}}]}
{"transaction":{"client_ip":"22.2.2.2","time_stamp":"Thu Jun 26 05:33:00 2025","request":{"uri":"/test3"}},"messages":[{"message":"Another block for 22.2.2.2","details":{"ruleId":"1003"}}]}';

    $firewallService = new FirewallService;

    // Act: Usar reflection para filtrar solo por IP 22.2.2.2
    $reflection = new ReflectionClass(FirewallService::class);
    $method = $reflection->getMethod('processModSecurityJson');
    $method->setAccessible(true);

    $filteredResult = $method->invoke($firewallService, $mixedIpJson, '22.2.2.2');

    // Assert: Solo debe contener logs de 22.2.2.2
    expect($filteredResult)->not->toBeEmpty()
        ->toContain('22.2.2.2')
        ->toContain('/test1')
        ->toContain('/test3')
        ->toContain('1001')
        ->toContain('1003')
        ->not->toContain('111.68.6.186')  // NO debe contener la otra IP
        ->not->toContain('/test2')        // NO debe contener URI de otra IP
        ->not->toContain('1002');         // NO debe contener ruleId de otra IP

    // Verificar que hay exactamente 2 líneas con la IP objetivo
    $lines = explode("\n", $filteredResult);
    $ipLines = array_filter($lines, fn ($line) => str_contains($line, 'IP: 22.2.2.2'));
    expect(count($ipLines))->toBe(2);

})->group('unit', 'modsecurity', 'critical');

test('returns empty when target ip not found in modsecurity logs', function () {
    // Arrange: JSON solo con otra IP (estructura REAL: messages[] en nivel raíz)
    $otherIpJson = '{"transaction":{"client_ip":"111.68.6.186","time_stamp":"Thu Jun 26 05:32:00 2025","request":{"uri":"/test"}},"messages":[{"message":"Some attack","details":{"ruleId":"9999"}}]}';

    $firewallService = new FirewallService;

    // Act: Buscar IP que no existe en los logs
    $reflection = new ReflectionClass(FirewallService::class);
    $method = $reflection->getMethod('processModSecurityJson');
    $method->setAccessible(true);

    $result = $method->invoke($firewallService, $otherIpJson, '22.2.2.2');

    // Assert: Debe retornar vacío porque 22.2.2.2 no está en los logs
    expect($result)->toBeEmpty();

})->group('unit', 'modsecurity', 'critical');

test('processes all logs when no target ip specified', function () {
    // Arrange: JSON con múltiples IPs (estructura REAL: messages[] en nivel raíz)
    $mixedIpJson = '{"transaction":{"client_ip":"22.2.2.2","time_stamp":"Thu Jun 26 05:31:50 2025","request":{"uri":"/test1"}},"messages":[{"message":"Test 1","details":{"ruleId":"1001"}}]}
{"transaction":{"client_ip":"111.68.6.186","time_stamp":"Thu Jun 26 05:32:00 2025","request":{"uri":"/test2"}},"messages":[{"message":"Test 2","details":{"ruleId":"1002"}}]}';

    $firewallService = new FirewallService;

    // Act: Sin especificar IP objetivo (empty string simulates no filtering)
    $reflection = new ReflectionClass(FirewallService::class);
    $method = $reflection->getMethod('processModSecurityJson');
    $method->setAccessible(true);

    $result = $method->invoke($firewallService, $mixedIpJson, '');

    // Assert: Debe procesar ambas IPs cuando no hay filtro específico
    expect($result)->not->toBeEmpty()
        ->toContain('22.2.2.2')
        ->toContain('111.68.6.186')
        ->toContain('/test1')
        ->toContain('/test2')
        ->toContain('1001')
        ->toContain('1002');

})->group('unit', 'modsecurity');
