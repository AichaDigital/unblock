<?php

declare(strict_types=1);

use App\Actions\Firewall\ValidateUserAccessToHostAction;
use App\Models\{Host, Hosting, User, UserHostingPermission};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

// ============================================================================
// SCENARIO 1: Admin Users
// ============================================================================

test('admin users have access to all hosts', function () {
    $adminUser = User::factory()->admin()->create();
    $host = Host::factory()->create();

    Log::spy();

    // Should not throw exception
    ValidateUserAccessToHostAction::run($adminUser, $host);

    // Verify debug log was called
    Log::shouldHaveReceived('debug')
        ->once()
        ->with('User has admin access to all hosts', [
            'user_id' => $adminUser->id,
            'host_id' => $host->id,
        ]);
});

// ============================================================================
// SCENARIO 2: Regular Users with Hosting Permission
// ============================================================================

test('regular users with hosting permission can access host', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $host = Host::factory()->create();

    // Create hosting for user on this host
    $hosting = Hosting::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
    ]);

    // Create active permission linking user to hosting
    UserHostingPermission::factory()->create([
        'user_id' => $user->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    Log::spy();

    // Should not throw exception
    ValidateUserAccessToHostAction::run($user, $host);

    // Verify debug log was called
    Log::shouldHaveReceived('debug')
        ->once()
        ->with('User has access to host', [
            'user_id' => $user->id,
            'host_id' => $host->id,
        ]);
});

test('regular users with multiple hostings on same host have access', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $host = Host::factory()->create();

    // Create multiple hostings for same user on same host
    $hosting1 = Hosting::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
    ]);

    $hosting2 = Hosting::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
    ]);

    UserHostingPermission::factory()->create([
        'user_id' => $user->id,
        'hosting_id' => $hosting1->id,
        'is_active' => true,
    ]);

    UserHostingPermission::factory()->create([
        'user_id' => $user->id,
        'hosting_id' => $hosting2->id,
        'is_active' => true,
    ]);

    // Should not throw exception
    ValidateUserAccessToHostAction::run($user, $host);

    expect(true)->toBeTrue(); // Assertion to confirm no exception
});

// ============================================================================
// SCENARIO 3: Regular Users WITHOUT Permission
// ============================================================================

test('regular users without permission cannot access host', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $host = Host::factory()->create();

    // No permissions created

    Log::spy();

    // Should throw exception
    expect(fn () => ValidateUserAccessToHostAction::run($user, $host))
        ->toThrow(Exception::class, "Access denied: User {$user->id} does not have permission for host {$host->id}");

    // Verify warning log was called
    Log::shouldHaveReceived('warning')
        ->once()
        ->with('User access denied to host', [
            'user_id' => $user->id,
            'host_id' => $host->id,
            'host_fqdn' => $host->fqdn,
        ]);
});

test('regular users with hosting on different host cannot access', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $hostA = Host::factory()->create(['fqdn' => 'host-a.example.com']);
    $hostB = Host::factory()->create(['fqdn' => 'host-b.example.com']);

    // User has permission on hostA
    $hosting = Hosting::factory()->create([
        'user_id' => $user->id,
        'host_id' => $hostA->id,
    ]);

    UserHostingPermission::factory()->create([
        'user_id' => $user->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    // Try to access hostB (should fail)
    expect(fn () => ValidateUserAccessToHostAction::run($user, $hostB))
        ->toThrow(Exception::class, "Access denied: User {$user->id} does not have permission for host {$hostB->id}");
});

test('regular users with inactive permission cannot access host', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $host = Host::factory()->create();

    $hosting = Hosting::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
    ]);

    // Create INACTIVE permission
    UserHostingPermission::factory()->create([
        'user_id' => $user->id,
        'hosting_id' => $hosting->id,
        'is_active' => false, // ← Inactive
    ]);

    // Should throw exception because permission is inactive
    expect(fn () => ValidateUserAccessToHostAction::run($user, $host))
        ->toThrow(Exception::class, "Access denied: User {$user->id} does not have permission for host {$host->id}");
});

// ============================================================================
// SCENARIO 4: Delegated Users (Parent User Permissions)
// ============================================================================

test('delegated users inherit access from parent user', function () {
    // Create parent user with access
    $parentUser = User::factory()->create(['is_admin' => false]);
    $host = Host::factory()->create();

    $hosting = Hosting::factory()->create([
        'user_id' => $parentUser->id,
        'host_id' => $host->id,
    ]);

    UserHostingPermission::factory()->create([
        'user_id' => $parentUser->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    // Create delegated user (child)
    $delegatedUser = User::factory()->create([
        'is_admin' => false,
        'parent_user_id' => $parentUser->id,
    ]);

    // Delegated user should have access through parent
    ValidateUserAccessToHostAction::run($delegatedUser, $host);

    expect(true)->toBeTrue(); // Assertion to confirm no exception
});

test('delegated users without parent permission cannot access', function () {
    $parentUser = User::factory()->create(['is_admin' => false]);
    $host = Host::factory()->create();

    // Parent has NO permission on this host

    $delegatedUser = User::factory()->create([
        'is_admin' => false,
        'parent_user_id' => $parentUser->id,
    ]);

    // Should throw exception
    expect(fn () => ValidateUserAccessToHostAction::run($delegatedUser, $host))
        ->toThrow(Exception::class, "Access denied: User {$delegatedUser->id} does not have permission for host {$host->id}");
});

// ============================================================================
// SCENARIO 5: Edge Cases
// ============================================================================

test('action logs debug when admin user accesses host', function () {
    $adminUser = User::factory()->admin()->create();
    $host = Host::factory()->create();

    Log::spy();

    ValidateUserAccessToHostAction::run($adminUser, $host);

    Log::shouldHaveReceived('debug')
        ->with('User has admin access to all hosts', Mockery::any());
});

test('action logs debug when regular user with permission accesses host', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $host = Host::factory()->create();

    $hosting = Hosting::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
    ]);

    UserHostingPermission::factory()->create([
        'user_id' => $user->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    Log::spy();

    ValidateUserAccessToHostAction::run($user, $host);

    Log::shouldHaveReceived('debug')
        ->with('User has access to host', [
            'user_id' => $user->id,
            'host_id' => $host->id,
        ]);
});

test('action logs warning when user access is denied', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $host = Host::factory()->create(['fqdn' => 'denied-host.example.com']);

    Log::spy();

    try {
        ValidateUserAccessToHostAction::run($user, $host);
    } catch (Exception $e) {
        // Expected exception
    }

    Log::shouldHaveReceived('warning')
        ->with('User access denied to host', [
            'user_id' => $user->id,
            'host_id' => $host->id,
            'host_fqdn' => 'denied-host.example.com',
        ]);
});

test('exception message includes user and host IDs', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $host = Host::factory()->create();

    try {
        ValidateUserAccessToHostAction::run($user, $host);
        $this->fail('Expected exception was not thrown');
    } catch (Exception $e) {
        expect($e->getMessage())
            ->toContain("User {$user->id}")
            ->toContain("host {$host->id}")
            ->toContain('Access denied');
    }
});

// ============================================================================
// SCENARIO 6: Multiple Users, Same Host
// ============================================================================

test('different users can have independent access to same host', function () {
    $host = Host::factory()->create();

    // User A with access
    $userA = User::factory()->create(['is_admin' => false]);
    $hostingA = Hosting::factory()->create([
        'user_id' => $userA->id,
        'host_id' => $host->id,
    ]);
    UserHostingPermission::factory()->create([
        'user_id' => $userA->id,
        'hosting_id' => $hostingA->id,
        'is_active' => true,
    ]);

    // User B WITHOUT access
    $userB = User::factory()->create(['is_admin' => false]);

    // User A should have access
    ValidateUserAccessToHostAction::run($userA, $host);
    expect(true)->toBeTrue();

    // User B should NOT have access
    expect(fn () => ValidateUserAccessToHostAction::run($userB, $host))
        ->toThrow(Exception::class);
});
