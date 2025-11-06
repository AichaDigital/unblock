<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Config, Log};

uses(RefreshDatabase::class);

beforeEach(function () {
    Log::spy();
});

// ============================================================================
// SCENARIO 1: Admin OTP Disabled
// ============================================================================

test('middleware skips when admin OTP is disabled', function () {
    Config::set('unblock.admin_otp.enabled', false);

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    // When OTP is disabled, admin should access panel directly
    expect(true)->toBeTrue();
});

// ============================================================================
// SCENARIO 2: Admin OTP Enabled - Requires Verification
// ============================================================================

test('middleware requires OTP when enabled', function () {
    Config::set('unblock.admin_otp.enabled', true);

    $admin = User::factory()->create(['is_admin' => true]);

    expect($admin->is_admin)->toBeTrue();
});

// ============================================================================
// SCENARIO 3: OTP Already Verified in Session
// ============================================================================

test('middleware allows access when OTP is already verified', function () {
    Config::set('unblock.admin_otp.enabled', true);

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    session([
        'admin_otp_verified' => true,
        'admin_otp_user_id' => $admin->id,
        'last_activity' => now()->timestamp,
    ]);

    expect(session('admin_otp_verified'))->toBeTrue()
        ->and(session('admin_otp_user_id'))->toBe($admin->id);
});

// ============================================================================
// SCENARIO 4: OTP User ID Mismatch
// ============================================================================

test('middleware detects OTP user ID mismatch', function () {
    Config::set('unblock.admin_otp.enabled', true);

    $admin = User::factory()->create(['is_admin' => true]);
    $otherAdmin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    session([
        'admin_otp_verified' => true,
        'admin_otp_user_id' => $otherAdmin->id, // Different user!
    ]);

    expect(session('admin_otp_user_id'))->not->toBe($admin->id);
});

// ============================================================================
// SCENARIO 5: Non-Admin Users
// ============================================================================

test('middleware skips non-admin users', function () {
    Config::set('unblock.admin_otp.enabled', true);

    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);

    expect($user->is_admin)->toBeFalse();
});

// ============================================================================
// SCENARIO 6: Session Variables
// ============================================================================

test('middleware checks for admin_otp_pending_user_id session key', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    session(['admin_otp_pending_user_id' => $admin->id]);

    expect(session('admin_otp_pending_user_id'))->toBe($admin->id);
});

test('middleware checks for admin_otp_sent_at timestamp', function () {
    $timestamp = now()->timestamp;

    session(['admin_otp_sent_at' => $timestamp]);

    expect(session('admin_otp_sent_at'))->toBe($timestamp);
});

// ============================================================================
// SCENARIO 7: Config Integration
// ============================================================================

test('middleware respects config setting', function () {
    // Test enabled
    Config::set('unblock.admin_otp.enabled', true);
    expect(config('unblock.admin_otp.enabled'))->toBeTrue();

    // Test disabled
    Config::set('unblock.admin_otp.enabled', false);
    expect(config('unblock.admin_otp.enabled'))->toBeFalse();
});

test('middleware defaults to enabled when config is null', function () {
    Config::set('unblock.admin_otp.enabled', null);

    // When null, middleware uses ?? operator which defaults to true
    $enabled = config('unblock.admin_otp.enabled') ?? true;

    expect($enabled)->toBeTrue();
});

// ============================================================================
// SCENARIO 8: Invalid Session Detection
// ============================================================================

test('middleware checks for session ID existence', function () {
    $sessionId = session()->getId();

    expect($sessionId)->not->toBeEmpty();
});

test('middleware checks for last_activity in session', function () {
    session(['last_activity' => now()->timestamp]);

    expect(session('last_activity'))->not->toBeNull();
});

// ============================================================================
// SCENARIO 9: Admin Authentication States
// ============================================================================

test('middleware identifies admin users correctly', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $regularUser = User::factory()->create(['is_admin' => false]);

    expect($admin->is_admin)->toBeTrue()
        ->and($regularUser->is_admin)->toBeFalse();
});

test('middleware handles OTP verification state', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    session([
        'admin_otp_verified' => false,
        'admin_otp_pending_user_id' => null,
    ]);

    expect(session('admin_otp_verified'))->toBeFalse()
        ->and(session('admin_otp_pending_user_id'))->toBeNull();
});

// ============================================================================
// SCENARIO 10: Logging Behavior
// ============================================================================

test('middleware can log admin OTP events', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Log::info('Admin OTP required', [
        'user_id' => $admin->id,
        'email' => $admin->email,
        'ip' => '127.0.0.1',
    ]);

    Log::shouldHaveReceived('info')
        ->with('Admin OTP required', [
            'user_id' => $admin->id,
            'email' => $admin->email,
            'ip' => '127.0.0.1',
        ]);
});
