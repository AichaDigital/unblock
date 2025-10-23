<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\artisan;

beforeEach(function () {
    // Clean database before each test
    User::query()->delete();
});

test('user:create command creates admin user in production mode', function () {
    artisan('user:create', ['--admin' => true])
        ->expectsQuestion('Email address', 'admin@test.com')
        ->expectsQuestion('First name', 'John')
        ->expectsQuestion('Last name', 'Admin')
        ->expectsQuestion('Company name (optional)', 'Test Company')
        ->expectsQuestion('Password', 'P@ssw0rd!Complex123')
        ->expectsQuestion('Confirm password', 'P@ssw0rd!Complex123')
        ->expectsConfirmation('Create this user?', 'yes')
        ->assertSuccessful();

    // Verify user was created
    $user = User::where('email', 'admin@test.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->first_name)->toBe('John')
        ->and($user->last_name)->toBe('Admin')
        ->and($user->company_name)->toBe('Test Company')
        ->and($user->is_admin)->toBeTrue()
        ->and(Hash::check('P@ssw0rd!Complex123', $user->password))->toBeTrue();
});

test('user:create command creates normal user in production mode', function () {
    artisan('user:create')
        ->expectsConfirmation('Create an admin user?', 'no')
        ->expectsQuestion('Email address', 'user@test.com')
        ->expectsQuestion('First name', 'Jane')
        ->expectsQuestion('Last name', 'Doe')
        ->expectsQuestion('Company name (optional)', '')
        ->expectsQuestion('Password', 'MyS3cure!Pass123')
        ->expectsQuestion('Confirm password', 'MyS3cure!Pass123')
        ->expectsConfirmation('Create this user?', 'yes')
        ->assertSuccessful();

    // Verify user was created
    $user = User::where('email', 'user@test.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->first_name)->toBe('Jane')
        ->and($user->last_name)->toBe('Doe')
        ->and($user->company_name)->toBeIn([null, '']) // Empty string or null are both valid
        ->and($user->is_admin)->toBeFalse()
        ->and(Hash::check('MyS3cure!Pass123', $user->password))->toBeTrue();
});

test('user:create command creates user with simple password in development mode', function () {
    artisan('user:create', ['--admin' => true, '--no-secure' => true])
        ->expectsQuestion('Email address', 'dev@test.com')
        ->expectsQuestion('First name', 'Dev')
        ->expectsQuestion('Last name', 'User')
        ->expectsQuestion('Company name (optional)', '')
        ->expectsQuestion('Password', 'password')
        ->expectsConfirmation('Create this user?', 'yes')
        ->assertSuccessful();

    // Verify user was created with simple password
    $user = User::where('email', 'dev@test.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->first_name)->toBe('Dev')
        ->and($user->last_name)->toBe('User')
        ->and($user->is_admin)->toBeTrue()
        ->and(Hash::check('password', $user->password))->toBeTrue();
});

test('user:create command rejects duplicate email', function () {
    // Create existing user
    User::factory()->create(['email' => 'existing@test.com']);

    artisan('user:create', ['--admin' => true])
        ->expectsQuestion('Email address', 'existing@test.com')
        ->assertFailed();
});

test('user:create command rejects invalid email', function () {
    artisan('user:create')
        ->expectsConfirmation('Create an admin user?', 'no')
        ->expectsQuestion('Email address', 'not-an-email')
        ->assertFailed();
});

test('user:create command validates password mismatch in production mode', function () {
    artisan('user:create', ['--admin' => true])
        ->expectsQuestion('Email address', 'test@test.com')
        ->expectsQuestion('First name', 'Test')
        ->expectsQuestion('Last name', 'User')
        ->expectsQuestion('Company name (optional)', '')
        ->expectsQuestion('Password', 'P@ssw0rd!Complex123')
        ->expectsQuestion('Confirm password', 'DifferentP@ss123!')
        ->assertFailed();

    // Verify user was NOT created
    expect(User::where('email', 'test@test.com')->exists())->toBeFalse();
});

test('user:create command allows cancellation', function () {
    artisan('user:create', ['--admin' => true])
        ->expectsQuestion('Email address', 'cancel@test.com')
        ->expectsQuestion('First name', 'Cancel')
        ->expectsQuestion('Last name', 'User')
        ->expectsQuestion('Company name (optional)', '')
        ->expectsQuestion('Password', 'P@ssw0rd!Complex123')
        ->expectsQuestion('Confirm password', 'P@ssw0rd!Complex123')
        ->expectsConfirmation('Create this user?', 'no')
        ->assertSuccessful();

    // Verify user was NOT created
    expect(User::where('email', 'cancel@test.com')->exists())->toBeFalse();
});

test('make:filament-user command is disabled and shows helpful message', function () {
    artisan('make:filament-user')
        ->expectsOutputToContain('This command has been disabled')
        ->expectsOutputToContain('user:create --admin')
        ->expectsOutputToContain('user:authorize')
        ->assertFailed();
});
