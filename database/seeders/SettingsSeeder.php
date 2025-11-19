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
        $settings = [
            'company_logo' => null, // Will be uploaded via admin panel
            'company_name' => env('COMPANY_NAME', 'Your Company'),
            'support_email' => env('SUPPORT_EMAIL', 'support@example.com'),
            'support_url' => env('SUPPORT_URL', 'https://support.example.com'),
            'privacy_policy_url' => env('LEGAL_PRIVACY_URL', ''),
            'terms_url' => env('LEGAL_TERMS_URL', ''),
            'data_protection_url' => env('LEGAL_DATA_PROTECTION_URL', ''),
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}

