<?php

declare(strict_types=1);

use App\Models\Setting;
use Database\Seeders\SettingsSeeder;

test('settings seeder populates values from company config', function () {
    // Regression (AID-342 M3): the seeder must read from config/company.php
    // (env()-backed, cache-safe) rather than calling env() directly, which
    // returns null once the configuration is cached.
    config()->set('company.name', 'Acme Test Co');
    config()->set('company.support.email', 'help@example.com');
    config()->set('company.support.url', 'https://support.example.com');
    config()->set('company.legal.privacy_policy_url', 'https://example.com/privacy');

    $this->seed(SettingsSeeder::class);

    expect(Setting::where('key', 'company_name')->value('value'))->toBe('Acme Test Co')
        ->and(Setting::where('key', 'support_email')->value('value'))->toBe('help@example.com')
        ->and(Setting::where('key', 'support_url')->value('value'))->toBe('https://support.example.com')
        ->and(Setting::where('key', 'privacy_policy_url')->value('value'))->toBe('https://example.com/privacy');
});
