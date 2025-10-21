<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Anonymous User Service
 *
 * Manages the system anonymous user for simple unblock mode.
 * This user represents anonymous requests that don't require authentication.
 *
 * Using a dedicated user maintains referential integrity in the database
 * while clearly identifying anonymous operations.
 */
class AnonymousUserService
{
    /**
     * System anonymous user email
     */
    private const ANONYMOUS_EMAIL = 'anonymous@system.internal';

    /**
     * Cached anonymous user instance
     */
    private static ?User $anonymousUser = null;

    /**
     * Get or create the anonymous system user
     */
    public static function get(): User
    {
        if (self::$anonymousUser === null) {
            self::$anonymousUser = User::firstOrCreate(
                ['email' => self::ANONYMOUS_EMAIL],
                [
                    'first_name' => 'Anonymous',
                    'last_name' => 'System',
                    'password' => bcrypt(Str::random(64)), // Unpredictable password
                    'is_admin' => false,
                ]
            );
        }

        return self::$anonymousUser;
    }

    /**
     * Check if a user is the anonymous system user
     */
    public static function isAnonymous(User $user): bool
    {
        return $user->email === self::ANONYMOUS_EMAIL;
    }

    /**
     * Get the anonymous user email
     */
    public static function getEmail(): string
    {
        return self::ANONYMOUS_EMAIL;
    }

    /**
     * Clear cached anonymous user (useful for testing)
     */
    public static function clearCache(): void
    {
        self::$anonymousUser = null;
    }
}
