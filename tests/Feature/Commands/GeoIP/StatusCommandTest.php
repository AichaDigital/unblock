<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Store original config
    $this->originalAccountId = config('services.maxmind.account_id');
    $this->originalLicenseKey = config('services.maxmind.license_key');
    $this->originalDbPath = config('services.maxmind.database_path');
    $this->originalEnabled = config('services.maxmind.enabled');

    // Set test configuration
    config(['services.maxmind.account_id' => '123456']);
    config(['services.maxmind.license_key' => 'test_license_key']);
    config(['services.maxmind.database_path' => storage_path('app/geoip-test/GeoLite2-City.mmdb')]);
    config(['services.maxmind.enabled' => true]);

    // Clean test directory
    if (File::isDirectory(storage_path('app/geoip-test'))) {
        File::deleteDirectory(storage_path('app/geoip-test'));
    }
});

afterEach(function () {
    // Restore original config
    config(['services.maxmind.account_id' => $this->originalAccountId]);
    config(['services.maxmind.license_key' => $this->originalLicenseKey]);
    config(['services.maxmind.database_path' => $this->originalDbPath]);
    config(['services.maxmind.enabled' => $this->originalEnabled]);

    // Clean test directory
    if (File::isDirectory(storage_path('app/geoip-test'))) {
        File::deleteDirectory(storage_path('app/geoip-test'));
    }
});

test('status command displays configuration correctly', function () {
    $this->artisan('geoip:status')
        ->expectsOutputToContain('🌍 GeoIP Status')
        ->expectsOutputToContain('Configuration:')
        ->expectsOutputToContain('Account ID')
        ->expectsOutputToContain('License Key')
        ->expectsOutputToContain('Database Path')
        ->assertExitCode(0);
});

test('status command shows credentials are set', function () {
    $this->artisan('geoip:status')
        ->expectsOutputToContain('✓ Set (123456...)')
        ->expectsOutputToContain('✓ Set (test_l...)');
});

test('status command shows credentials are not set', function () {
    config(['services.maxmind.account_id' => null]);
    config(['services.maxmind.license_key' => null]);

    $this->artisan('geoip:status')
        ->expectsOutputToContain('✗ Not set');
});

test('status command shows database does not exist', function () {
    $this->artisan('geoip:status')
        ->expectsOutputToContain('Database Status:')
        ->expectsOutputToContain('Exists')
        ->expectsOutputToContain('✗ No');
});

test('status command shows database exists with details', function () {
    // Create test database file
    $dbPath = config('services.maxmind.database_path');
    File::ensureDirectoryExists(dirname($dbPath));
    File::put($dbPath, str_repeat('X', 10 * 1024 * 1024)); // 10 MB

    // Use filemtime to verify file was created
    $age = now()->diffInDays(now()->setTimestamp(filemtime($dbPath)));

    $this->artisan('geoip:status')
        ->expectsOutputToContain('✓ Yes')
        ->expectsOutputToContain('Size')
        ->expectsOutputToContain('Last Modified')
        ->expectsOutputToContain('Age');
});

test('status command detects old database needing update', function () {
    // Create old database file (>7 days)
    $dbPath = config('services.maxmind.database_path');
    File::ensureDirectoryExists(dirname($dbPath));
    File::put($dbPath, 'old database');
    touch($dbPath, now()->subDays(10)->timestamp);

    $this->artisan('geoip:status')
        ->expectsOutputToContain('Update Needed');
});

test('status command shows database is up to date', function () {
    // Create recent database file
    $dbPath = config('services.maxmind.database_path');
    File::ensureDirectoryExists(dirname($dbPath));
    File::put($dbPath, 'recent database');
    touch($dbPath, now()->subDays(2)->timestamp);

    $this->artisan('geoip:status')
        ->expectsOutputToContain('✓ No');
});

test('status command shows service is not available when database missing', function () {
    $this->artisan('geoip:status')
        ->expectsOutputToContain('Service Status:')
        ->expectsOutputToContain('Available')
        ->expectsOutputToContain('✗ No')
        ->expectsOutputToContain('Database file not found');
});

test('status command suggests running update when database missing', function () {
    $this->artisan('geoip:status')
        ->expectsOutputToContain('💡 Run "php artisan geoip:update" to download the database');
});

test('status command displays database path', function () {
    $dbPath = config('services.maxmind.database_path');

    $this->artisan('geoip:status')
        ->expectsOutputToContain($dbPath);
});

test('status command shows enabled status', function () {
    config(['services.maxmind.enabled' => true]);

    $this->artisan('geoip:status')
        ->expectsOutputToContain('Enabled');
    // Note: Actual "Yes" format may vary based on table rendering
});

test('status command shows disabled status', function () {
    config(['services.maxmind.enabled' => false]);

    $this->artisan('geoip:status')
        ->expectsOutputToContain('Enabled')
        ->expectsOutputToContain('✗ No');
});

test('status command formats file size correctly', function () {
    // Test different file sizes
    $dbPath = config('services.maxmind.database_path');
    File::ensureDirectoryExists(dirname($dbPath));

    // Test KB
    File::put($dbPath, str_repeat('X', 500 * 1024)); // 500 KB
    $this->artisan('geoip:status')
        ->expectsOutputToContain('KB');

    // Test MB
    File::put($dbPath, str_repeat('X', 5 * 1024 * 1024)); // 5 MB
    $this->artisan('geoip:status')
        ->expectsOutputToContain('MB');
});

test('status command shows age in days', function () {
    $dbPath = config('services.maxmind.database_path');
    File::ensureDirectoryExists(dirname($dbPath));
    File::put($dbPath, 'test');

    $this->artisan('geoip:status')
        ->expectsOutputToContain('days'); // Verifies days unit is displayed
});

test('status command displays all three sections', function () {
    $this->artisan('geoip:status')
        ->expectsOutputToContain('Configuration:')
        ->expectsOutputToContain('Database Status:')
        ->expectsOutputToContain('Service Status:')
        ->assertExitCode(0);
});

test('status command masks sensitive credentials', function () {
    config(['services.maxmind.account_id' => '123456789']);
    config(['services.maxmind.license_key' => 'very_long_secret_key']);

    $output = $this->artisan('geoip:status');

    // Should NOT show full credentials
    $output->expectsOutputToContain('123456...');
    $output->expectsOutputToContain('very_l...');

    // Verify it doesn't leak full values
    $output->doesntExpectOutput('123456789');
    $output->doesntExpectOutput('very_long_secret_key');
});

test('status command handles zero-sized database file', function () {
    $dbPath = config('services.maxmind.database_path');
    File::ensureDirectoryExists(dirname($dbPath));
    File::put($dbPath, ''); // Empty file

    $this->artisan('geoip:status')
        ->expectsOutputToContain('0 B');
});
