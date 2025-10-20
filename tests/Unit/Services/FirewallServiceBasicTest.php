<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\{InvalidIpException, InvalidKeyException};
use App\Models\Host;
use App\Services\FirewallService;
use Tests\FirewallTestConstants as TC;

test('validates IP format correctly', function () {
    $service = new FirewallService;
    $host = new Host(['fqdn' => TC::TEST_HOST_FQDN, 'port_ssh' => 22]);

    expect(fn () => $service->checkProblems(
        $host,
        TC::TEST_SSH_KEY,
        'csf',
        'invalid-ip'
    ))->toThrow(InvalidIpException::class);
});

test('throws exception for unknown command', function () {
    $service = new FirewallService;
    $host = new Host(['fqdn' => TC::TEST_HOST_FQDN, 'port_ssh' => 22]);

    expect(fn () => $service->checkProblems(
        $host,
        TC::TEST_SSH_KEY,
        'unknown_command',
        TC::TEST_BLOCKED_IP
    ))->toThrow(InvalidKeyException::class);
});

test('builds csf command correctly', function () {
    $service = new FirewallService;

    // Use reflection to test buildCommand method
    $reflection = new \ReflectionClass($service);
    $method = $reflection->getMethod('buildCommand');
    $method->setAccessible(true);

    $result = $method->invoke($service, 'csf', TC::TEST_BLOCKED_IP);

    expect($result)->toBe('csf -g '.TC::TEST_BLOCKED_IP);
});

test('builds da bfm check command correctly', function () {
    $service = new FirewallService;

    $reflection = new \ReflectionClass($service);
    $method = $reflection->getMethod('buildCommand');
    $method->setAccessible(true);

    $result = $method->invoke($service, 'da_bfm_check', TC::TEST_BLOCKED_IP);
    $escapedIp = escapeshellarg(TC::TEST_BLOCKED_IP);

    expect($result)->toBe("cat /usr/local/directadmin/data/admin/ip_blacklist | grep {$escapedIp} || true");
});

test('returns null for invalid command', function () {
    $service = new FirewallService;

    $reflection = new \ReflectionClass($service);
    $method = $reflection->getMethod('buildCommand');
    $method->setAccessible(true);

    $result = $method->invoke($service, 'invalid_command', TC::TEST_BLOCKED_IP);

    expect($result)->toBeNull();
});
