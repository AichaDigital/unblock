<?php

declare(strict_types=1);

use App\Models\{Host, Hosting, User, UserHostingPermission};
use Illuminate\Database\Eloquent\Relations\{HasMany, HasManyThrough};

// --- Accessors (lines 169-177) ---

test('full_name accessor returns first and last name concatenated', function () {
    $user = User::factory()->make([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    expect($user->full_name)->toBe('John Doe');
});

test('name accessor returns same value as full_name', function () {
    $user = User::factory()->make([
        'first_name' => 'Jane',
        'last_name' => 'Smith',
    ]);

    expect($user->name)->toBe('Jane Smith')
        ->and($user->name)->toBe($user->full_name);
});

// --- Relationships (lines 214-243) ---

test('reports relationship returns HasMany instance', function () {
    $user = User::factory()->create();

    expect($user->reports())->toBeInstanceOf(HasMany::class);
});

test('hostingPermissions relationship returns HasMany instance', function () {
    $user = User::factory()->create();

    expect($user->hostingPermissions())->toBeInstanceOf(HasMany::class);
});

test('authorizedUserHostingPermissions relationship returns HasManyThrough instance', function () {
    $user = User::factory()->create();

    expect($user->authorizedUserHostingPermissions())
        ->toBeInstanceOf(HasManyThrough::class);
});

test('authorizedHostings relationship returns only active hosting permissions', function () {
    $user = User::factory()->create();
    $host = Host::factory()->create();

    $activeHosting = Hosting::factory()->create(['user_id' => $user->id, 'host_id' => $host->id]);
    $inactiveHosting = Hosting::factory()->create(['user_id' => $user->id, 'host_id' => $host->id]);

    UserHostingPermission::create([
        'user_id' => $user->id,
        'hosting_id' => $activeHosting->id,
        'is_active' => true,
    ]);

    UserHostingPermission::create([
        'user_id' => $user->id,
        'hosting_id' => $inactiveHosting->id,
        'is_active' => false,
    ]);

    $authorizedHostings = $user->authorizedHostings;

    expect($authorizedHostings)->toHaveCount(1)
        ->and($authorizedHostings->first()->id)->toBe($activeHosting->id);
});

// --- hasAccessToHost (line 254-298) ---

test('admin user has access to any host', function () {
    $admin = User::factory()->admin()->create();
    $host = Host::factory()->create();

    expect($admin->hasAccessToHost($host->id))->toBeTrue();
});

test('user with direct host permission has access', function () {
    $user = User::factory()->create();
    $host = Host::factory()->create();

    $user->hosts()->attach($host->id, ['is_active' => true]);

    expect($user->hasAccessToHost($host->id))->toBeTrue();
});

test('user without any permission has no access to host', function () {
    $user = User::factory()->create();
    $host = Host::factory()->create();

    expect($user->hasAccessToHost($host->id))->toBeFalse();
});

test('authorized user inherits parent direct host permission', function () {
    $parent = User::factory()->create();
    $host = Host::factory()->create();
    $parent->hosts()->attach($host->id, ['is_active' => true]);

    $child = User::factory()->authorizedUser($parent->id)->create();

    expect($child->hasAccessToHost($host->id))->toBeTrue();
});

test('user with hosting permission on host has access', function () {
    $user = User::factory()->create();
    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create(['user_id' => $user->id, 'host_id' => $host->id]);

    UserHostingPermission::create([
        'user_id' => $user->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    expect($user->hasAccessToHost($host->id))->toBeTrue();
});

test('authorized user inherits parent hosting permission', function () {
    $parent = User::factory()->create();
    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create(['user_id' => $parent->id, 'host_id' => $host->id]);

    UserHostingPermission::create([
        'user_id' => $parent->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    $child = User::factory()->authorizedUser($parent->id)->create();

    expect($child->hasAccessToHost($host->id))->toBeTrue();
});

// --- hasAccessToHosting (line 303-323) ---

test('admin user has access to any hosting', function () {
    $admin = User::factory()->admin()->create();
    $hosting = Hosting::factory()->create();

    expect($admin->hasAccessToHosting($hosting->id))->toBeTrue();
});

test('principal user who owns hosting has access', function () {
    $user = User::factory()->create();
    $hosting = Hosting::factory()->create(['user_id' => $user->id]);

    expect($user->hasAccessToHosting($hosting->id))->toBeTrue();
});

test('principal user without ownership nor permission has no access to hosting', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $hosting = Hosting::factory()->create(['user_id' => $otherUser->id]);

    expect($user->hasAccessToHosting($hosting->id))->toBeFalse();
});

test('user with specific hosting permission has access', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $hosting = Hosting::factory()->create(['user_id' => $otherUser->id]);

    UserHostingPermission::create([
        'user_id' => $user->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    expect($user->hasAccessToHosting($hosting->id))->toBeTrue();
});

test('authorized user without specific permission has no ownership access to hosting', function () {
    $parent = User::factory()->create();
    $hosting = Hosting::factory()->create(['user_id' => $parent->id]);

    $child = User::factory()->authorizedUser($parent->id)->create();

    // Authorized user does NOT inherit ownership — only specific permissions
    expect($child->hasAccessToHosting($hosting->id))->toBeFalse();
});

// --- Booted lifecycle: cascade delete/restore (lines 76-100) ---

test('deleting parent user cascades soft delete to authorized users and their hostings', function () {
    $parent = User::factory()->create();
    $child = User::factory()->authorizedUser($parent->id)->create();
    $hosting = Hosting::factory()->create(['user_id' => $child->id]);

    $parent->delete();

    expect($child->fresh()->trashed())->toBeTrue()
        ->and($hosting->fresh()->trashed())->toBeTrue();
});

test('restoring parent user cascades restore to authorized users and their hostings', function () {
    $parent = User::factory()->create();
    $child = User::factory()->authorizedUser($parent->id)->create();
    $hosting = Hosting::factory()->create(['user_id' => $child->id]);

    $parent->delete();

    // Verify they are deleted
    expect($child->fresh()->trashed())->toBeTrue()
        ->and($hosting->fresh()->trashed())->toBeTrue();

    $parent->restore();

    expect($child->fresh()->trashed())->toBeFalse()
        ->and($hosting->fresh()->trashed())->toBeFalse();
});

test('deleting non-parent user does not cascade', function () {
    $parent = User::factory()->create();
    $child = User::factory()->authorizedUser($parent->id)->create();

    // Deleting the child should NOT affect the parent
    $child->delete();

    expect($parent->fresh()->trashed())->toBeFalse();
});
