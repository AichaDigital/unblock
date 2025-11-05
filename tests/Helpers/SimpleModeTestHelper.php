<?php

declare(strict_types=1);

namespace Tests\Helpers;

/**
 * Simple Mode Test Helper
 *
 * Provides helper methods for tests that need to work with the dual-mode architecture.
 *
 * Usage:
 * ```php
 * use Tests\Helpers\SimpleModeTestHelper;
 *
 * test('my feature works in simple mode', function () {
 *     SimpleModeTestHelper::enableSimpleMode();
 *     // Your test code here
 * });
 * ```
 *
 * @see .cursor/rules/unblock-dual-mode-architecture.mdc
 */
class SimpleModeTestHelper
{
    /**
     * Enable Simple Mode for the current test
     *
     * Sets config('unblock.simple_mode.enabled') to true and configures
     * default simple mode settings for testing.
     */
    public static function enableSimpleMode(): void
    {
        config()->set('unblock.simple_mode.enabled', true);
        config()->set('unblock.simple_mode.whitelist_ttl', 3600);
        config()->set('unblock.simple_mode.throttle_per_minute', 3);
        config()->set('unblock.simple_mode.throttle_email_per_hour', 5);
        config()->set('unblock.simple_mode.throttle_domain_per_hour', 10);
        config()->set('unblock.simple_mode.throttle_subnet_per_hour', 20);
        config()->set('unblock.simple_mode.throttle_global_per_hour', 500);
    }

    /**
     * Disable Simple Mode for the current test (Admin Mode)
     *
     * Sets config('unblock.simple_mode.enabled') to false.
     */
    public static function disableSimpleMode(): void
    {
        config()->set('unblock.simple_mode.enabled', false);
    }

    /**
     * Check if Simple Mode is currently enabled
     */
    public static function isSimpleModeEnabled(): bool
    {
        return config('unblock.simple_mode.enabled', false) === true;
    }

    /**
     * Create a temporary user (Simple Mode user)
     */
    public static function createTemporaryUser(string $email = 'simple@example.com'): \App\Models\User
    {
        return \App\Models\User::factory()->create([
            'first_name' => 'Simple',
            'last_name' => 'Unblock',
            'email' => $email,
            'is_admin' => false,
        ]);
    }

    /**
     * Create a real admin user (Admin Mode user)
     */
    public static function createAdminUser(string $email = 'admin@example.com'): \App\Models\User
    {
        return \App\Models\User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => $email,
            'is_admin' => true,
        ]);
    }

    /**
     * Assert that Simple Mode is enabled
     *
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    public static function assertSimpleModeEnabled(): void
    {
        if (! self::isSimpleModeEnabled()) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                'Failed asserting that Simple Mode is enabled. '.
                'Did you forget to call SimpleModeTestHelper::enableSimpleMode()?'
            );
        }
    }

    /**
     * Assert that Simple Mode is disabled (Admin Mode)
     *
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    public static function assertSimpleModeDisabled(): void
    {
        if (self::isSimpleModeEnabled()) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                'Failed asserting that Simple Mode is disabled (Admin Mode). '.
                'Did you forget to call SimpleModeTestHelper::disableSimpleMode()?'
            );
        }
    }

    /**
     * Configure Simple Mode with custom settings
     *
     * @param  array<string, mixed>  $settings
     */
    public static function configureSimpleMode(array $settings = []): void
    {
        self::enableSimpleMode();

        foreach ($settings as $key => $value) {
            config()->set("unblock.simple_mode.{$key}", $value);
        }
    }
}
