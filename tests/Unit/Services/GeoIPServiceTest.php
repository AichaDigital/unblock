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

test('service handles relative path correctly', function () {
    // Simulate production environment where path is relative but file doesn't exist
    config(['services.maxmind.enabled' => true]);
    config(['services.maxmind.database_path' => 'storage/app/geoip/NonExistent.mmdb']);

    $service = new GeoIPService;

    // Service should normalize the path to absolute path using base_path()
    // Since file doesn't exist, service should be disabled but path should be normalized
    expect($service->isAvailable())->toBeFalse();

    $info = $service->getDatabaseInfo();
    expect($info['available'])->toBeFalse();
    expect($info['path'])->toBe(base_path('storage/app/geoip/NonExistent.mmdb'));
});

test('service handles relative path with existing file correctly', function () {
    // Test that relative paths work when file exists
    config(['services.maxmind.enabled' => true]);
    config(['services.maxmind.database_path' => 'storage/app/geoip/GeoLite2-City.mmdb']);

    $service = new GeoIPService;

    // If file exists, service should be available
    if (file_exists(base_path('storage/app/geoip/GeoLite2-City.mmdb'))) {
        expect($service->isAvailable())->toBeTrue();

        $info = $service->getDatabaseInfo();
        expect($info['available'])->toBeTrue();
        expect($info['path'])->toBe(base_path('storage/app/geoip/GeoLite2-City.mmdb'));
    } else {
        expect($service->isAvailable())->toBeFalse();
    }
});

test('service handles absolute path correctly', function () {
    // Test that absolute paths are not modified
    $absolutePath = '/absolute/path/to/GeoLite2-City.mmdb';

    config(['services.maxmind.enabled' => true]);
    config(['services.maxmind.database_path' => $absolutePath]);

    $service = new GeoIPService;

    $info = $service->getDatabaseInfo();
    expect($info['path'])->toBe($absolutePath);
});
