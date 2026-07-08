<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Seed application settings from environment variables.
     *
     * These settings represent the owner/operator's configuration for the Unblock application.
     * They can be edited later via the Filament admin panel.
     */
    public function run(): void
    {
        // Read from config/company.php (which is env()-backed) so the values are
        // still resolved correctly when the configuration is cached.
        $settings = [
            'company_logo' => null, // Will be uploaded via admin panel
            'company_name' => config('company.name'),
            'support_email' => config('company.support.email'),
            'support_url' => config('company.support.url'),
            'privacy_policy_url' => config('company.legal.privacy_policy_url'),
            'terms_url' => config('company.legal.terms_url'),
            'data_protection_url' => config('company.legal.data_protection_url'),
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}

