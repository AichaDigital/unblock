<?php

/**
 * Tests for Account and Domain model scopes - Reduced Version
 * Only tests scopes that exist in the models
 */

use App\Models\{Account, Domain};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Account Scopes', function () {
    test('active scope excludes suspended and deleted accounts', function () {
        Account::factory()->create(['suspended_at' => null, 'deleted_at' => null]); // Active
        Account::factory()->create(['suspended_at' => now(), 'deleted_at' => null]); // Suspended
        Account::factory()->create(['suspended_at' => null, 'deleted_at' => now()]); // Deleted

        $activeAccounts = Account::active()->get();

        expect($activeAccounts)->toHaveCount(1);
    });

    test('suspended scope returns only suspended accounts', function () {
        Account::factory()->create(['suspended_at' => null]); // Active
        Account::factory()->create(['suspended_at' => now()]);
        Account::factory()->create(['suspended_at' => now()]);

        $suspendedAccounts = Account::suspended()->get();

        expect($suspendedAccounts)->toHaveCount(2);
    });

    test('markedAsDeleted scope returns only deleted accounts', function () {
        Account::factory()->create(['deleted_at' => null]); // Active
        Account::factory()->create(['deleted_at' => now()]);

        $deletedAccounts = Account::markedAsDeleted()->get();

        expect($deletedAccounts)->toHaveCount(1);
    });
});

describe('Account State Detection', function () {
    test('account with suspended_at is considered suspended', function () {
        $active = Account::factory()->create(['suspended_at' => null]);
        $suspended = Account::factory()->create(['suspended_at' => now()]);

        expect($active->suspended_at)->toBeNull()
            ->and($suspended->suspended_at)->not->toBeNull();
    });

    test('account with deleted_at is considered deleted', function () {
        $active = Account::factory()->create(['deleted_at' => null]);
        $deleted = Account::factory()->create(['deleted_at' => now()]);

        expect($active->deleted_at)->toBeNull()
            ->and($deleted->deleted_at)->not->toBeNull();
    });
});
