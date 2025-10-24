<?php

declare(strict_types=1);

use App\Services\GeoIPService;

beforeEach(function () {
    // Store original config
    $this->originalEnabled = config('services.maxmind.enabled');
    $this->originalPath = config('services.maxmind.database_path');
});

afterEach(function () {
    // Restore original config
    config(['services.maxmind.enabled' => $this->originalEnabled]);
    config(['services.maxmind.database_path' => $this->originalPath]);
});

test('service returns null when disabled', function () {
    config(['services.maxmind.enabled' => false]);

    $service = new GeoIPService;
    $result = $service->lookup('8.8.8.8');

    expect($result)->toBeNull();
    expect($service->isAvailable())->toBeFalse();
});

test('service returns null when database file does not exist', function () {
    config(['services.maxmind.enabled' => true]);
    config(['services.maxmind.database_path' => '/nonexistent/path/GeoLite2-City.mmdb']);

    $service = new GeoIPService;
    $result = $service->lookup('8.8.8.8');

    expect($result)->toBeNull();
    expect($service->isAvailable())->toBeFalse();
});

test('service returns null for private IP addresses', function () {
    // Even if service is enabled, private IPs should return null
    $service = new GeoIPService;

    $privateIps = [
        '192.168.1.1',
        '10.0.0.1',
        '172.16.0.1',
        '127.0.0.1',
        'localhost',
        '::1',
    ];

    foreach ($privateIps as $ip) {
        $result = $service->lookup($ip);
        expect($result)->toBeNull();
    }
});

test('database info returns correct structure when disabled', function () {
    config(['services.maxmind.enabled' => false]);

    $service = new GeoIPService;
    $info = $service->getDatabaseInfo();

    expect($info)->toBeArray();
    expect($info)->toHaveKey('available');
    expect($info)->toHaveKey('path');
    expect($info)->toHaveKey('exists');
    expect($info['available'])->toBeFalse();
});

test('database info returns correct structure when file missing', function () {
    config(['services.maxmind.enabled' => true]);
    config(['services.maxmind.database_path' => '/nonexistent/path/GeoLite2-City.mmdb']);

    $service = new GeoIPService;
    $info = $service->getDatabaseInfo();

    expect($info)->toBeArray();
    expect($info['available'])->toBeFalse();
    expect($info['exists'])->toBeFalse();
});
