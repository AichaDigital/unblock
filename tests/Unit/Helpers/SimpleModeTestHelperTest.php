<?php

declare(strict_types=1);

use Tests\Helpers\SimpleModeTestHelper;

test('SimpleModeTestHelper can enable simple mode', function () {
    SimpleModeTestHelper::enableSimpleMode();

    expect(config('unblock.simple_mode.enabled'))->toBeTrue()
        ->and(config('unblock.simple_mode.whitelist_ttl'))->toBe(3600)
        ->and(config('unblock.simple_mode.throttle_per_minute'))->toBe(3);
});

test('SimpleModeTestHelper can disable simple mode', function () {
    SimpleModeTestHelper::disableSimpleMode();

    expect(config('unblock.simple_mode.enabled'))->toBeFalse();
});

test('SimpleModeTestHelper can check if simple mode is enabled', function () {
    SimpleModeTestHelper::enableSimpleMode();
    expect(SimpleModeTestHelper::isSimpleModeEnabled())->toBeTrue();

    SimpleModeTestHelper::disableSimpleMode();
    expect(SimpleModeTestHelper::isSimpleModeEnabled())->toBeFalse();
});

test('SimpleModeTestHelper can create temporary user', function () {
    $user = SimpleModeTestHelper::createTemporaryUser('test@example.com');

    expect($user->first_name)->toBe('Simple')
        ->and($user->last_name)->toBe('Unblock')
        ->and($user->email)->toBe('test@example.com')
        ->and($user->is_admin)->toBeFalse();
});

test('SimpleModeTestHelper can create admin user', function () {
    $user = SimpleModeTestHelper::createAdminUser('admin@example.com');

    expect($user->first_name)->toBe('Test')
        ->and($user->last_name)->toBe('Admin')
        ->and($user->email)->toBe('admin@example.com')
        ->and($user->is_admin)->toBeTrue();
});

test('SimpleModeTestHelper can configure simple mode with custom settings', function () {
    SimpleModeTestHelper::configureSimpleMode([
        'whitelist_ttl' => 7200,
        'throttle_per_minute' => 5,
    ]);

    expect(config('unblock.simple_mode.enabled'))->toBeTrue()
        ->and(config('unblock.simple_mode.whitelist_ttl'))->toBe(7200)
        ->and(config('unblock.simple_mode.throttle_per_minute'))->toBe(5);
});

test('global helper function enableSimpleMode works', function () {
    enableSimpleMode();

    expect(config('unblock.simple_mode.enabled'))->toBeTrue();
});

test('global helper function disableSimpleMode works', function () {
    disableSimpleMode();

    expect(config('unblock.simple_mode.enabled'))->toBeFalse();
});

test('global helper function createTemporaryUser works', function () {
    $user = createTemporaryUser('temp@example.com');

    expect($user->first_name)->toBe('Simple')
        ->and($user->last_name)->toBe('Unblock')
        ->and($user->email)->toBe('temp@example.com');
});

test('global helper function createAdminUser works', function () {
    $user = createAdminUser('admin@example.com');

    expect($user->first_name)->toBe('Test')
        ->and($user->last_name)->toBe('Admin')
        ->and($user->email)->toBe('admin@example.com');
});

test('SimpleModeTestHelper assertions work correctly', function () {
    SimpleModeTestHelper::enableSimpleMode();
    SimpleModeTestHelper::assertSimpleModeEnabled();

    SimpleModeTestHelper::disableSimpleMode();
    SimpleModeTestHelper::assertSimpleModeDisabled();
})->expect(fn () => true)->toBeTrue();
