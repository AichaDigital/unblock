<?php

declare(strict_types=1);

use App\Models\{Hosting, User, UserHostingPermission};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ============================================================================
// SCENARIO 1: Model Structure
// ============================================================================

test('model has correct fillable attributes', function () {
    $permission = new UserHostingPermission;
    $fillable = $permission->getFillable();

    expect($fillable)->toContain('user_id')
        ->and($fillable)->toContain('hosting_id')
        ->and($fillable)->toContain('is_active');
});

test('model casts is_active as boolean', function () {
    $permission = new UserHostingPermission;
    $casts = $permission->getCasts();

    expect($casts['is_active'])->toBe('boolean');
});

// ============================================================================
// SCENARIO 2: Relationships
// ============================================================================

test('user relationship is defined', function () {
    $user = User::factory()->create();
    $hosting = Hosting::factory()->create();

    $permission = UserHostingPermission::factory()->create([
        'user_id' => $user->id,
        'hosting_id' => $hosting->id,
    ]);

    expect($permission->user)->not->toBeNull()
        ->and($permission->user->id)->toBe($user->id);
});

test('hosting relationship is defined', function () {
    $user = User::factory()->create();
    $hosting = Hosting::factory()->create();

    $permission = UserHostingPermission::factory()->create([
        'user_id' => $user->id,
        'hosting_id' => $hosting->id,
    ]);

    expect($permission->hosting)->not->toBeNull()
        ->and($permission->hosting->id)->toBe($hosting->id);
});

// ============================================================================
// SCENARIO 3: isActive Method
// ============================================================================

test('isActive returns true for active permissions', function () {
    $permission = UserHostingPermission::factory()->create(['is_active' => true]);

    expect($permission->isActive())->toBeTrue();
});

test('isActive returns false for inactive permissions', function () {
    $permission = UserHostingPermission::factory()->create(['is_active' => false]);

    expect($permission->isActive())->toBeFalse();
});

// ============================================================================
// SCENARIO 4: Scopes
// ============================================================================

test('active scope filters active permissions', function () {
    UserHostingPermission::factory()->create(['is_active' => true]);
    UserHostingPermission::factory()->create(['is_active' => true]);
    UserHostingPermission::factory()->create(['is_active' => false]);

    $active = UserHostingPermission::active()->get();

    expect($active)->toHaveCount(2);
});

test('forUser scope filters permissions by user', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $hosting1 = Hosting::factory()->create();
    $hosting2 = Hosting::factory()->create();

    UserHostingPermission::factory()->create([
        'user_id' => $user1->id,
        'hosting_id' => $hosting1->id,
    ]);
    UserHostingPermission::factory()->create([
        'user_id' => $user1->id,
        'hosting_id' => $hosting2->id,
    ]);
    UserHostingPermission::factory()->create([
        'user_id' => $user2->id,
        'hosting_id' => $hosting1->id,
    ]);

    $user1Permissions = UserHostingPermission::forUser($user1->id)->get();

    expect($user1Permissions)->toHaveCount(2);
});

test('forHosting scope filters permissions by hosting', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $hosting1 = Hosting::factory()->create();
    $hosting2 = Hosting::factory()->create();

    UserHostingPermission::factory()->create([
        'user_id' => $user1->id,
        'hosting_id' => $hosting1->id,
    ]);
    UserHostingPermission::factory()->create([
        'user_id' => $user2->id,
        'hosting_id' => $hosting1->id,
    ]);
    UserHostingPermission::factory()->create([
        'user_id' => $user1->id,
        'hosting_id' => $hosting2->id,
    ]);

    $hosting1Permissions = UserHostingPermission::forHosting($hosting1->id)->get();

    expect($hosting1Permissions)->toHaveCount(2);
});

test('scopes can be combined', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $hosting1 = Hosting::factory()->create();
    $hosting2 = Hosting::factory()->create();
    $hosting3 = Hosting::factory()->create();

    // Active permission for user1 on hosting1
    UserHostingPermission::factory()->create([
        'user_id' => $user1->id,
        'hosting_id' => $hosting1->id,
        'is_active' => true,
    ]);

    // Inactive permission for user1 on hosting3
    UserHostingPermission::factory()->create([
        'user_id' => $user1->id,
        'hosting_id' => $hosting3->id,
        'is_active' => false,
    ]);

    // Active permission for user1 on hosting2
    UserHostingPermission::factory()->create([
        'user_id' => $user1->id,
        'hosting_id' => $hosting2->id,
        'is_active' => true,
    ]);

    // Active permission for user2 on hosting1
    UserHostingPermission::factory()->create([
        'user_id' => $user2->id,
        'hosting_id' => $hosting1->id,
        'is_active' => true,
    ]);

    $results = UserHostingPermission::active()
        ->forUser($user1->id)
        ->forHosting($hosting1->id)
        ->get();

    expect($results)->toHaveCount(1);
});

// ============================================================================
// SCENARIO 5: Boolean Cast
// ============================================================================

test('is_active is cast to boolean true', function () {
    $permission = UserHostingPermission::factory()->create(['is_active' => 1]);

    expect($permission->is_active)->toBeTrue()
        ->and($permission->is_active)->toBeBool();
});

test('is_active is cast to boolean false', function () {
    $permission = UserHostingPermission::factory()->create(['is_active' => 0]);

    expect($permission->is_active)->toBeFalse()
        ->and($permission->is_active)->toBeBool();
});

// ============================================================================
// SCENARIO 6: Edge Cases
// ============================================================================

test('can create multiple permissions for same user', function () {
    $user = User::factory()->create();
    $hosting1 = Hosting::factory()->create();
    $hosting2 = Hosting::factory()->create();

    UserHostingPermission::factory()->create([
        'user_id' => $user->id,
        'hosting_id' => $hosting1->id,
    ]);
    UserHostingPermission::factory()->create([
        'user_id' => $user->id,
        'hosting_id' => $hosting2->id,
    ]);

    expect(UserHostingPermission::forUser($user->id)->count())->toBe(2);
});

test('can create multiple permissions for same hosting', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $hosting = Hosting::factory()->create();

    UserHostingPermission::factory()->create([
        'user_id' => $user1->id,
        'hosting_id' => $hosting->id,
    ]);
    UserHostingPermission::factory()->create([
        'user_id' => $user2->id,
        'hosting_id' => $hosting->id,
    ]);

    expect(UserHostingPermission::forHosting($hosting->id)->count())->toBe(2);
});
