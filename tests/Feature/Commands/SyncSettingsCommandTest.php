<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\assertDatabaseHas;

test('settings:sync command syncs values from env to database', function () {
    // Set env values for testing
    config([
        'company.name' => 'Test Company from ENV',
        'company.support.email' => 'support@test.com',
        'company.support.url' => 'https://support.test.com',
        'company.legal.privacy_policy_url' => 'https://test.com/privacy',
        'company.legal.terms_url' => 'https://test.com/terms',
        'company.legal.data_protection_url' => 'https://test.com/data',
    ]);

    // Run command
    Artisan::call('settings:sync');

    // Verify settings were synced
    assertDatabaseHas('settings', [
        'key' => 'company_name',
        'value' => 'Test Company from ENV',
    ]);

    assertDatabaseHas('settings', [
        'key' => 'support_email',
        'value' => 'support@test.com',
    ]);
});

test('settings:sync command updates existing settings', function () {
    // Create initial setting
    Setting::create([
        'key' => 'company_name',
        'value' => 'Old Company Name',
    ]);

    // Set new env value
    config(['company.name' => 'New Company Name']);

    // Run command
    Artisan::call('settings:sync');

    // Verify setting was updated
    assertDatabaseHas('settings', [
        'key' => 'company_name',
        'value' => 'New Company Name',
    ]);

    // Should only have one record
    expect(Setting::where('key', 'company_name')->count())->toBe(1);
});

test('settings:sync command returns success exit code', function () {
    $exitCode = Artisan::call('settings:sync');

    expect($exitCode)->toBe(0);
});

test('settings:sync command output shows synced settings', function () {
    config(['company.name' => 'Test Company']);

    Artisan::call('settings:sync');
    $output = Artisan::output();

    expect($output)
        ->toContain('Syncing settings')
        ->toContain('Settings synced successfully');
});
