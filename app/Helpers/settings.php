<?php

declare(strict_types=1);
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

if (! function_exists('setting')) {
    /**
     * Get or set application settings.
     *
     * This function manages application-wide settings stored in the database.
     * Settings are cached for 10 minutes to improve performance.
     *
     * @param  string|array  $key  Setting key or array of key-value pairs to set
     * @param  mixed  $default  Default value if setting not found (only when getting)
     */
    function setting(string|array $key, mixed $default = null): mixed
    {
        if (is_array($key)) {
            // Set multiple settings
            foreach ($key as $k => $v) {
                Setting::updateOrCreate(
                    ['key' => $k],
                    ['value' => $v]
                );
            }
            Cache::forget('app_settings');

            return null;
        }

        // Get setting with cache (10 minutes)
        $settings = Cache::remember('app_settings', 600, function () {
            return Setting::pluck('value', 'key')->toArray();
        });

        return $settings[$key] ?? $default;
    }
}
