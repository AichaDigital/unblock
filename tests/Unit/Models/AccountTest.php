<?php

declare(strict_types=1);

use App\Models\{Account, Domain, Host, User};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ============================================================================
// SCENARIO 1: Model Structure
// ============================================================================

test('model has correct fillable attributes', function () {
    $account = new Account;
    $fillable = $account->getFillable();

    expect($fillable)->toContain('host_id')
        ->and($fillable)->toContain('user_id')
        ->and($fillable)->toContain('username')
        ->and($fillable)->toContain('domain');
});

test('model casts dates correctly', function () {
    $account = new Account;
    $casts = $account->getCasts();

    expect($casts['suspended_at'])->toBe('datetime')
        ->and($casts['deleted_at'])->toBe('datetime')
        ->and($casts['last_synced_at'])->toBe('datetime');
});

// ============================================================================
// SCENARIO 2: Relationships (Line 74 needs coverage)
// ============================================================================

test('host relationship is defined', function () {
    $host = Host::factory()->create();
    $account = Account::factory()->create(['host_id' => $host->id]);

    expect($account->host)->not->toBeNull()
        ->and($account->host->id)->toBe($host->id);
});

test('user relationship is defined', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    expect($account->user)->not->toBeNull()
        ->and($account->user->id)->toBe($user->id);
});

test('user relationship returns null when user_id is null', function () {
    $account = Account::factory()->create(['user_id' => null]);

    expect($account->user)->toBeNull();
});

test('domains relationship is defined', function () {
    $account = Account::factory()->create();
    Domain::factory()->count(3)->create(['account_id' => $account->id]);

    expect($account->domains)->toHaveCount(3);
});

// ============================================================================
// SCENARIO 3: Scopes
// ============================================================================

test('active scope filters active accounts', function () {
    Account::factory()->create(['suspended_at' => null, 'deleted_at' => null]);
    Account::factory()->create(['suspended_at' => now(), 'deleted_at' => null]);
    Account::factory()->create(['suspended_at' => null, 'deleted_at' => now()]);

    $active = Account::active()->get();

    expect($active)->toHaveCount(1);
});

test('suspended scope filters suspended accounts', function () {
    Account::factory()->create(['suspended_at' => null]);
    Account::factory()->create(['suspended_at' => now()]);
    Account::factory()->create(['suspended_at' => now()]);

    $suspended = Account::suspended()->get();

    expect($suspended)->toHaveCount(2);
});

test('markedAsDeleted scope filters deleted accounts', function () {
    Account::factory()->create(['deleted_at' => null]);
    Account::factory()->create(['deleted_at' => now()]);
    Account::factory()->create(['deleted_at' => now()]);

    $deleted = Account::markedAsDeleted()->get();

    expect($deleted)->toHaveCount(2);
});
