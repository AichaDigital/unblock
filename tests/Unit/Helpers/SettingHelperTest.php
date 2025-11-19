<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    // Clear settings cache before each test
    Cache::forget('app_settings');
});

test('setting helper can set a single setting', function () {
    setting(['company_name' => 'Test Company']);

    assertDatabaseHas('settings', [
        'key' => 'company_name',
        'value' => 'Test Company',
    ]);
});

test('setting helper can set multiple settings at once', function () {
    setting([
        'company_name' => 'Test Company',
        'support_email' => 'support@test.com',
    ]);

    assertDatabaseHas('settings', [
        'key' => 'company_name',
        'value' => 'Test Company',
    ]);

    assertDatabaseHas('settings', [
        'key' => 'support_email',
        'value' => 'support@test.com',
    ]);
});

test('setting helper can get a setting value', function () {
    Setting::create([
        'key' => 'company_name',
        'value' => 'Test Company',
    ]);

    $value = setting('company_name');

    expect($value)->toBe('Test Company');
});

test('setting helper returns default when setting does not exist', function () {
    $value = setting('non_existent_key', 'default_value');

    expect($value)->toBe('default_value');
});

test('setting helper returns null when setting does not exist and no default provided', function () {
    $value = setting('non_existent_key');

    expect($value)->toBeNull();
});

test('setting helper caches settings for performance', function () {
    Setting::create([
        'key' => 'company_name',
        'value' => 'Test Company',
    ]);

    // First call - queries database
    $firstCall = setting('company_name');

    // Manually delete from database (cache should still have it)
    Setting::where('key', 'company_name')->delete();

    // Second call - should use cache
    $secondCall = setting('company_name');

    expect($firstCall)->toBe($secondCall)->toBe('Test Company');
});

test('setting helper clears cache when updating a setting', function () {
    Setting::create([
        'key' => 'company_name',
        'value' => 'Old Company',
    ]);

    // Load into cache
    setting('company_name');

    // Update setting
    setting(['company_name' => 'New Company']);

    // Should return new value (cache cleared)
    $value = setting('company_name');

    expect($value)->toBe('New Company');
});

test('Setting model clears cache on save', function () {
    $setting = Setting::create([
        'key' => 'company_name',
        'value' => 'Test Company',
    ]);

    // Load into cache
    setting('company_name');

    // Update directly via model
    $setting->update(['value' => 'Updated Company']);

    // Cache should be cleared, new value returned
    $value = setting('company_name');

    expect($value)->toBe('Updated Company');
});

test('Setting model clears cache on delete', function () {
    $setting = Setting::create([
        'key' => 'company_name',
        'value' => 'Test Company',
    ]);

    // Load into cache
    setting('company_name');

    // Delete setting
    $setting->delete();

    // Cache should be cleared, null returned
    $value = setting('company_name');

    expect($value)->toBeNull();
});
