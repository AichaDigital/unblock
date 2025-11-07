<?php

declare(strict_types=1);

use App\Models\{Host, Hosting, User, UserHostingPermission};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ============================================================================
// SCENARIO 1: Model Structure
// ============================================================================

test('model has correct fillable attributes', function () {
    $hosting = new Hosting;
    $fillable = $hosting->getFillable();

    expect($fillable)->toContain('user_id')
        ->and($fillable)->toContain('host_id')
        ->and($fillable)->toContain('domain')
        ->and($fillable)->toContain('username')
        ->and($fillable)->toContain('hosting_manual');
});

test('model casts hosting_manual as boolean', function () {
    $hosting = new Hosting;
    $casts = $hosting->getCasts();

    expect($casts['hosting_manual'])->toBe('boolean');
});

test('model uses SoftDeletes', function () {
    $hosting = Hosting::factory()->create();

    $hosting->delete();

    expect($hosting->trashed())->toBeTrue();
});

// ============================================================================
// SCENARIO 2: Relationships (Lines 40-48, 64-67 need coverage)
// ============================================================================

test('user relationship is defined', function () {
    $user = User::factory()->create();
    $hosting = Hosting::factory()->create(['user_id' => $user->id]);

    expect($hosting->user)->not->toBeNull()
        ->and($hosting->user->id)->toBe($user->id);
});

test('host relationship is defined', function () {
    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create(['host_id' => $host->id]);

    expect($hosting->host)->not->toBeNull()
        ->and($hosting->host->id)->toBe($host->id);
});

test('hostingPermissions relationship is defined', function () {
    $hosting = Hosting::factory()->create();

    UserHostingPermission::factory()->count(3)->create(['hosting_id' => $hosting->id]);

    expect($hosting->hostingPermissions)->toHaveCount(3);
});

test('authorizedUsers relationship returns users with active permissions', function () {
    $hosting = Hosting::factory()->create();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user3 = User::factory()->create();

    // Create active permission
    UserHostingPermission::create([
        'user_id' => $user1->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    // Create active permission
    UserHostingPermission::create([
        'user_id' => $user2->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    // Create inactive permission
    UserHostingPermission::create([
        'user_id' => $user3->id,
        'hosting_id' => $hosting->id,
        'is_active' => false,
    ]);

    $authorizedUsers = $hosting->authorizedUsers;

    expect($authorizedUsers)->toHaveCount(2)
        ->and($authorizedUsers->pluck('id')->toArray())->toContain($user1->id)
        ->and($authorizedUsers->pluck('id')->toArray())->toContain($user2->id)
        ->and($authorizedUsers->pluck('id')->toArray())->not->toContain($user3->id);
});

test('authorizedUsers includes pivot data', function () {
    $hosting = Hosting::factory()->create();
    $user = User::factory()->create();

    UserHostingPermission::create([
        'user_id' => $user->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    $authorizedUser = $hosting->authorizedUsers->first();

    expect($authorizedUser->pivot)->not->toBeNull()
        ->and((bool) $authorizedUser->pivot->is_active)->toBeTrue();
});

test('authorizedUsers excludes inactive users', function () {
    $hosting = Hosting::factory()->create();
    $inactiveUser = User::factory()->create();

    UserHostingPermission::create([
        'user_id' => $inactiveUser->id,
        'hosting_id' => $hosting->id,
        'is_active' => false,
    ]);

    expect($hosting->authorizedUsers)->toHaveCount(0);
});

// ============================================================================
// SCENARIO 3: Boolean Cast
// ============================================================================

test('hosting_manual is cast to boolean true', function () {
    $hosting = Hosting::factory()->create(['hosting_manual' => 1]);

    expect($hosting->hosting_manual)->toBeTrue()
        ->and($hosting->hosting_manual)->toBeBool();
});

test('hosting_manual is cast to boolean false', function () {
    $hosting = Hosting::factory()->create(['hosting_manual' => 0]);

    expect($hosting->hosting_manual)->toBeFalse()
        ->and($hosting->hosting_manual)->toBeBool();
});

// ============================================================================
// SCENARIO 4: Soft Deletes
// ============================================================================

test('soft deleted hostings are excluded from default queries', function () {
    Hosting::factory()->create();
    $deleted = Hosting::factory()->create();
    $deleted->delete();

    expect(Hosting::count())->toBe(1)
        ->and(Hosting::withTrashed()->count())->toBe(2);
});

test('soft deleted hostings can be restored', function () {
    $hosting = Hosting::factory()->create();

    $hosting->delete();
    expect($hosting->trashed())->toBeTrue();

    $hosting->restore();
    expect($hosting->trashed())->toBeFalse();
});
