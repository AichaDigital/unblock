<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Panel;

beforeEach(function () {
    $this->panel = Mockery::mock(Panel::class);
});

test('non-admin users cannot access filament panel', function () {
    $user = User::factory()->create([
        'is_admin' => false,
        'email' => 'user@example.com',
    ]);

    expect($user->canAccessPanel($this->panel))->toBeFalse();
});

test('admin users can access panel when no whitelist is configured', function () {
    config()->set('filament.admin_whitelist.emails', []);
    config()->set('filament.admin_whitelist.domains', []);

    $user = User::factory()->create([
        'is_admin' => true,
        'email' => 'admin@example.com',
    ]);

    expect($user->canAccessPanel($this->panel))->toBeTrue();
});

test('admin users can access panel when email is in whitelist', function () {
    config()->set('filament.admin_whitelist.emails', ['admin@company.com', 'support@company.com']);
    config()->set('filament.admin_whitelist.domains', []);

    $user = User::factory()->create([
        'is_admin' => true,
        'email' => 'admin@company.com',
    ]);

    expect($user->canAccessPanel($this->panel))->toBeTrue();
});

test('admin users cannot access panel when email is not in whitelist', function () {
    config()->set('filament.admin_whitelist.emails', ['admin@company.com']);
    config()->set('filament.admin_whitelist.domains', []);

    $user = User::factory()->create([
        'is_admin' => true,
        'email' => 'other@example.com',
    ]);

    expect($user->canAccessPanel($this->panel))->toBeFalse();
});

test('admin users can access panel when domain is in whitelist', function () {
    config()->set('filament.admin_whitelist.emails', []);
    config()->set('filament.admin_whitelist.domains', ['company.com', 'holding.com']);

    $user = User::factory()->create([
        'is_admin' => true,
        'email' => 'anyone@company.com',
    ]);

    expect($user->canAccessPanel($this->panel))->toBeTrue();
});

test('admin users cannot access panel when domain is not in whitelist', function () {
    config()->set('filament.admin_whitelist.emails', []);
    config()->set('filament.admin_whitelist.domains', ['company.com']);

    $user = User::factory()->create([
        'is_admin' => true,
        'email' => 'admin@other-domain.com',
    ]);

    expect($user->canAccessPanel($this->panel))->toBeFalse();
});

test('admin users can access panel when either email or domain matches whitelist', function () {
    config()->set('filament.admin_whitelist.emails', ['specific@example.com']);
    config()->set('filament.admin_whitelist.domains', ['company.com']);

    // User with whitelisted email but different domain
    $user1 = User::factory()->create([
        'is_admin' => true,
        'email' => 'specific@example.com',
    ]);

    // User with whitelisted domain but different email
    $user2 = User::factory()->create([
        'is_admin' => true,
        'email' => 'another@company.com',
    ]);

    expect($user1->canAccessPanel($this->panel))->toBeTrue();
    expect($user2->canAccessPanel($this->panel))->toBeTrue();
});

test('whitelist is case sensitive for emails', function () {
    config()->set('filament.admin_whitelist.emails', ['Admin@Company.com']);
    config()->set('filament.admin_whitelist.domains', []);

    $user = User::factory()->create([
        'is_admin' => true,
        'email' => 'admin@company.com', // lowercase
    ]);

    // Emails are case-sensitive in our whitelist implementation
    expect($user->canAccessPanel($this->panel))->toBeFalse();
});

test('whitelist works with mixed configuration', function () {
    config()->set('filament.admin_whitelist.emails', ['admin@external.com']);
    config()->set('filament.admin_whitelist.domains', ['internal.com']);

    $adminExternal = User::factory()->create([
        'is_admin' => true,
        'email' => 'admin@external.com',
    ]);

    $userInternal = User::factory()->create([
        'is_admin' => true,
        'email' => 'user@internal.com',
    ]);

    $userBlocked = User::factory()->create([
        'is_admin' => true,
        'email' => 'blocked@blocked.com',
    ]);

    expect($adminExternal->canAccessPanel($this->panel))->toBeTrue();
    expect($userInternal->canAccessPanel($this->panel))->toBeTrue();
    expect($userBlocked->canAccessPanel($this->panel))->toBeFalse();
});
