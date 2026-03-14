<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\SimpleModeTestHelper;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Feature', 'Unit');

/**
 * Custom Pest helpers for Simple Mode testing
 */

/**
 * Enable Simple Mode for the current test
 *
 * Usage: $this->enableSimpleMode()
 */
function enableSimpleMode(): void
{
    SimpleModeTestHelper::enableSimpleMode();
}

/**
 * Disable Simple Mode for the current test (Admin Mode)
 *
 * Usage: $this->disableSimpleMode()
 */
function disableSimpleMode(): void
{
    SimpleModeTestHelper::disableSimpleMode();
}

/**
 * Create a temporary user (Simple Mode user)
 *
 * Usage: $user = $this->createTemporaryUser()
 */
function createTemporaryUser(string $email = 'simple@example.com'): User
{
    return SimpleModeTestHelper::createTemporaryUser($email);
}

/**
 * Create an admin user (Admin Mode user)
 *
 * Usage: $admin = $this->createAdminUser()
 */
function createAdminUser(string $email = 'admin@example.com'): User
{
    return SimpleModeTestHelper::createAdminUser($email);
}

/**
 * Login as a specific user
 *
 * Usage: loginAsUser($user)
 */
function loginAsUser(User $user): void
{
    test()->actingAs($user);
}

/**
 * Login as admin (creates and authenticates as admin user)
 *
 * Usage: loginAsAdmin()
 */
function loginAsAdmin(): User
{
    $admin = SimpleModeTestHelper::createAdminUser();
    test()->actingAs($admin);

    return $admin;
}

/**
 * Get the path to test resources
 *
 * Usage: testPath('stubs')
 */
function testPath(string $path = ''): string
{
    $basePath = __DIR__;

    return $path ? $basePath.'/'.ltrim($path, '/') : $basePath;
}
