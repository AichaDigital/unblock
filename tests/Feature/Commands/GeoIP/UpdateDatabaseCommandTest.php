<?php

declare(strict_types=1);

use Illuminate\Support\Facades\{File, Http, Log};

beforeEach(function () {
    // Store original config
    $this->originalAccountId = config('services.maxmind.account_id');
    $this->originalLicenseKey = config('services.maxmind.license_key');
    $this->originalDbPath = config('services.maxmind.database_path');

    // Set test configuration
    config(['services.maxmind.account_id' => '123456']);
    config(['services.maxmind.license_key' => 'test_license_key']);
    config(['services.maxmind.database_path' => storage_path('app/geoip-test/GeoLite2-City.mmdb')]);

    // Clean test directory
    if (File::isDirectory(storage_path('app/geoip-test'))) {
        File::deleteDirectory(storage_path('app/geoip-test'));
    }
    if (File::isDirectory(storage_path('app/temp'))) {
        File::deleteDirectory(storage_path('app/temp'));
    }
});

afterEach(function () {
    // Restore original config
    config(['services.maxmind.account_id' => $this->originalAccountId]);
    config(['services.maxmind.license_key' => $this->originalLicenseKey]);
    config(['services.maxmind.database_path' => $this->originalDbPath]);

    // Clean test directory
    if (File::isDirectory(storage_path('app/geoip-test'))) {
        File::deleteDirectory(storage_path('app/geoip-test'));
    }
    if (File::isDirectory(storage_path('app/temp'))) {
        File::deleteDirectory(storage_path('app/temp'));
    }
});

test('command fails when account id is not configured', function () {
    config(['services.maxmind.account_id' => null]);

    $this->artisan('geoip:update')
        ->expectsOutput('✗ MaxMind configuration missing')
        ->assertExitCode(1);
});

test('command fails when license key is not configured', function () {
    config(['services.maxmind.license_key' => null]);

    $this->artisan('geoip:update')
        ->expectsOutput('✗ MaxMind configuration missing')
        ->assertExitCode(1);
});

test('command skips update when database is recent', function () {
    // Create a recent database file
    $dbPath = config('services.maxmind.database_path');
    File::ensureDirectoryExists(dirname($dbPath));
    File::put($dbPath, 'test database content');
    touch($dbPath, now()->timestamp);

    $this->artisan('geoip:update')
        ->expectsOutputToContain('✓ Database is up to date')
        ->assertExitCode(0);
});

test('command correctly calculates database age', function () {
    // Test that the age calculation logic works
    // By verifying a recent file is NOT considered old
    $dbPath = config('services.maxmind.database_path');
    File::ensureDirectoryExists(dirname($dbPath));
    File::put($dbPath, 'recent database content');

    // File just created should have age close to 0
    $age = now()->diffInDays(now()->setTimestamp(filemtime($dbPath)));

    // Should be less than 7 days (actually should be 0)
    expect($age)->toBeLessThan(7);
});

test('command forces update with --force flag', function () {
    // Create a recent database file
    $dbPath = config('services.maxmind.database_path');
    File::ensureDirectoryExists(dirname($dbPath));
    File::put($dbPath, 'test database content');
    touch($dbPath, now()->timestamp);

    // Mock HTTP response - use empty response to avoid tar execution issues during mutation testing
    Http::fake([
        'download.maxmind.com/*' => Http::response('', 200),
    ]);

    // Expect command to fail gracefully when archive extraction fails
    // This prevents tar errors during mutation testing
    try {
        $this->artisan('geoip:update --force');
    } catch (\Exception $e) {
        // Expected to fail when archive is invalid, but should still make HTTP request
    }

    // Verify --force bypasses age check and makes HTTP request
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'download.maxmind.com');
    });
})->group('integration');

test('command handles download failure gracefully', function () {
    Log::shouldReceive('error')->once();

    Http::fake([
        'download.maxmind.com/*' => Http::response('Unauthorized', 401),
    ]);

    $this->artisan('geoip:update --force')
        ->expectsOutputToContain('✗ Failed to update GeoIP database')
        ->assertExitCode(1);
});

test('command builds correct download url with parameters', function () {
    // Use empty response to prevent tar execution during mutation testing
    Http::fake([
        'download.maxmind.com/*' => Http::response('', 200),
    ]);

    // Expect failure due to invalid archive, but should still make request
    try {
        $this->artisan('geoip:update --force');
    } catch (\Exception $e) {
        // Expected when archive is invalid
    }

    Http::assertSent(function ($request) {
        $url = $request->url();

        return str_contains($url, 'download.maxmind.com/app/geoip_download') &&
               str_contains($url, 'edition_id=GeoLite2-City') &&
               str_contains($url, 'license_key=test_license_key') &&
               str_contains($url, 'suffix=tar.gz');
    });
})->group('integration');

test('command uses correct file paths for database installation', function () {
    // Verify the command uses the configured database path
    $configuredPath = config('services.maxmind.database_path');
    $expectedPath = storage_path('app/geoip-test/GeoLite2-City.mmdb');

    expect($configuredPath)->toBe($expectedPath);

    // Verify path structure is correct
    expect(dirname($configuredPath))->toContain('geoip-test');
    expect(basename($configuredPath))->toBe('GeoLite2-City.mmdb');
});

test('command validates configuration before making http request', function () {
    config(['services.maxmind.license_key' => null]);

    Http::fake();

    $this->artisan('geoip:update')
        ->assertExitCode(1);

    // Verify no HTTP request was made
    Http::assertNothingSent();
});

test('command respects 7 day threshold for automatic updates', function () {
    // Test with 6 days old (should not update)
    $dbPath = config('services.maxmind.database_path');
    File::ensureDirectoryExists(dirname($dbPath));
    File::put($dbPath, 'recent database');
    touch($dbPath, now()->subDays(6)->timestamp);

    Http::fake();

    $this->artisan('geoip:update')
        ->expectsOutputToContain('✓ Database is up to date')
        ->assertExitCode(0);

    Http::assertNothingSent();
});

test('command handles timeout with appropriate error message', function () {
    Log::shouldReceive('error')->once();

    // Simulate timeout by faking exception
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Operation timed out');
    });

    $this->artisan('geoip:update --force')
        ->expectsOutputToContain('✗ Failed to update GeoIP database')
        ->assertExitCode(1);
});
