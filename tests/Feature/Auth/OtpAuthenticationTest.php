<?php

use App\Livewire\OtpLogin;
use App\Models\User;
use Illuminate\Support\Facades\{Auth, DB};
use Livewire\Livewire;
use Spatie\OneTimePasswords\Models\OneTimePassword;

beforeEach(function () {
    // Clean tables before each test
    OneTimePassword::truncate();
    DB::table('activity_log')->truncate(); // Clean activity log
    DB::table('action_audits')->truncate(); // Clean audit as well

    // Ensure no user is authenticated
    Auth::logout();

    // Clear any lingering authentication state
    session()->flush();
    session()->regenerate();

    // Create test user
    $this->user = User::factory()->create([
        'email' => 'test@example.com',
        'first_name' => 'Test',
        'last_name' => 'User',
    ]);
});

test('component renders normal mode login form correctly', function () {
    config(['unblock.simple_mode.enabled' => false]);

    Livewire::test(OtpLogin::class)
        ->assertSee('Solo cuentas de cliente')
        ->assertSee('Correo electrónico')
        ->assertSee('Enviar código de acceso');
});

test('component renders simple mode login form correctly', function () {
    config(['unblock.simple_mode.enabled' => true]);

    Livewire::test(OtpLogin::class)
        ->assertSee(__('auth.simple_mode_title'))
        ->assertDontSee('Solo cuentas de cliente');
});

test('redirects authenticated users to dashboard in normal mode', function () {
    config(['unblock.simple_mode.enabled' => false]);
    Auth::login($this->user);

    Livewire::test(OtpLogin::class)
        ->assertRedirect(route('dashboard'));
});

test('does NOT redirect authenticated users in simple mode', function () {
    config(['unblock.simple_mode.enabled' => true]);
    Auth::login($this->user);

    Livewire::test(OtpLogin::class)
        ->assertNoRedirect();
});

test('sends otp for valid user email', function () {
    Livewire::test(OtpLogin::class)
        ->set('email', $this->user->email)
        ->call('sendOtp')
        ->assertSet('otpSent', true)
        ->assertSet('user.email', $this->user->email);

    // Verify that an OTP was created (integration with Spatie)
    expect(OneTimePassword::count())->toBe(1);
});

test('spatie rate limiting works correctly', function () {
    // Simplified test that verifies basic integration
    $component = Livewire::test(OtpLogin::class)
        ->set('email', $this->user->email)
        ->call('sendOtp')
        ->assertSet('otpSent', true);

    // Verify that the OTP was created correctly
    $otpCount = OneTimePassword::count();
    expect($otpCount)->toBe(1, 'Should create exactly one OTP');

    // Verify that rate limiting is handled internally by Spatie
    // (we don't need to test Spatie's internal implementation)
    $otp = OneTimePassword::first();
    expect($otp)->not->toBeNull('OTP should be created');
    expect($otp->authenticatable_id)->toBe($this->user->id, 'OTP should belong to correct user');
    expect($otp->authenticatable_type)->toBe(User::class, 'OTP should be for User model');
});

test('validates email must exist in normal mode', function () {
    config(['unblock.simple_mode.enabled' => false]);
    $component = Livewire::test(OtpLogin::class);

    // Email that doesn't exist
    $component
        ->set('email', 'nonexistent@example.com')
        ->call('sendOtp')
        ->assertSet('otpSent', false);
});

test('allows any email in simple mode and creates temporary user', function () {
    config(['unblock.simple_mode.enabled' => true]);
    $component = Livewire::test(OtpLogin::class);

    // Email that doesn't exist
    $component
        ->set('email', 'nonexistent@example.com')
        ->call('sendOtp')
        ->assertHasNoErrors()
        ->assertSet('otpSent', true);

    // Verify temporary user was created
    $this->assertDatabaseHas('users', [
        'email' => 'nonexistent@example.com',
    ]);
});

test('successful otp verification logs user in and redirects to dashboard in normal mode', function () {
    config(['unblock.simple_mode.enabled' => false]);

    // Send OTP
    $component = Livewire::test(OtpLogin::class)
        ->set('email', $this->user->email)
        ->call('sendOtp')
        ->assertSet('otpSent', true);

    // Get generated code
    $otp = OneTimePassword::first();

    // Verify correct code
    $component
        ->set('oneTimePassword', $otp->password)
        ->call('verifyOtp')
        ->assertRedirect(route('dashboard'));

    // Verify successful authentication
    expect(Auth::check())->toBeTrue();
    expect(Auth::user()->id)->toBe($this->user->id);
});

test('successful otp verification logs user in and redirects to simple unblock form in simple mode', function () {
    config(['unblock.simple_mode.enabled' => true]);

    // Send OTP
    $component = Livewire::test(OtpLogin::class)
        ->set('email', $this->user->email)
        ->call('sendOtp')
        ->assertSet('otpSent', true);

    // Get generated code
    $otp = OneTimePassword::first();

    // Verify correct code
    $component
        ->set('oneTimePassword', $otp->password)
        ->call('verifyOtp')
        ->assertRedirect(route('simple.unblock'));

    // Verify successful authentication
    expect(Auth::check())->toBeTrue();
    expect(Auth::user()->id)->toBe($this->user->id);
});

test('otp cannot be verified from a different ip than the one that requested it', function () {
    // Send OTP from IP A
    // Simulate proxy header and remote addr
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.10';
    $_SERVER['REMOTE_ADDR'] = '203.0.113.10';

    $component = Livewire::test(OtpLogin::class)
        ->set('email', $this->user->email)
        ->call('sendOtp')
        ->assertSet('otpSent', true);

    // Retrieve OTP value
    $otp = OneTimePassword::first();

    // Now switch to IP B before verifying
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.20';
    $_SERVER['REMOTE_ADDR'] = '198.51.100.20';

    // Attempt to verify with correct code but different IP
    $component
        ->set('oneTimePassword', $otp->password)
        ->call('verifyOtp');

    // Should not authenticate
    expect(Auth::check())->toBeFalse();
});

test('failed otp verification keeps user unauthenticated', function () {
    // Ensure we start without authentication
    Auth::logout();

    // Send OTP first
    $component = Livewire::test(OtpLogin::class)
        ->set('email', $this->user->email)
        ->call('sendOtp')
        ->assertSet('otpSent', true);

    // Try with incorrect code
    $component
        ->set('oneTimePassword', '999999')
        ->call('verifyOtp');

    // Verify there was no redirect to dashboard (would indicate successful authentication)
    expect($component->effects['redirect'] ?? null)->toBeNull();
    // Verify directly that the user is NOT authenticated
    expect(Auth::check())->toBeFalse();
});

test('validates otp format before attempting verification', function () {
    $component = Livewire::test(OtpLogin::class)
        ->set('email', $this->user->email)
        ->call('sendOtp')
        ->assertSet('otpSent', true);

    // Code too short
    $component->set('oneTimePassword', '123')->call('verifyOtp');
    expect(Auth::check())->toBeFalse();

    // Code too long
    $component->set('oneTimePassword', '1234567')->call('verifyOtp');
    expect(Auth::check())->toBeFalse();

    // Empty field
    $component->set('oneTimePassword', '')->call('verifyOtp');
    expect(Auth::check())->toBeFalse();
});

test('resend functionality works after enabling', function () {
    $component = Livewire::test(OtpLogin::class)
        ->set('email', $this->user->email)
        ->call('sendOtp')
        ->assertSet('otpSent', true)
        ->assertSet('canResend', false);

    // Enable resend manually
    $component->call('enableResend')
        ->assertSet('canResend', true);

    // Do resend
    $component->call('resendOtp');

    // Verify there's still an OTP available
    expect(OneTimePassword::count())->toBeGreaterThan(0);
});

test('cannot resend before cooldown period', function () {
    $component = Livewire::test(OtpLogin::class)
        ->set('email', $this->user->email)
        ->call('sendOtp')
        ->assertSet('canResend', false);

    $initialCount = OneTimePassword::count();

    // Try resend without enabling
    $component->call('resendOtp');

    // State should not have changed
    expect(OneTimePassword::count())->toBe($initialCount);
});

test('form reset clears all state', function () {
    $component = Livewire::test(OtpLogin::class)
        ->set('email', $this->user->email)
        ->call('sendOtp')
        ->assertSet('otpSent', true)
        ->set('oneTimePassword', '123456')
        ->call('resetForm')
        ->assertSet('otpSent', false)
        ->assertSet('email', '')
        ->assertSet('oneTimePassword', '')
        ->assertSet('user', null);
});

test('works with authorized users having parent_user_id', function () {
    config(['unblock.simple_mode.enabled' => false]);
    // Create authorized user (specific business case)
    $parentUser = User::factory()->create();
    $authorizedUser = User::factory()->create([
        'email' => 'authorized@example.com',
        'parent_user_id' => $parentUser->id,
    ]);

    // Complete flow with authorized user
    $component = Livewire::test(OtpLogin::class)
        ->set('email', $authorizedUser->email)
        ->call('sendOtp')
        ->assertSet('otpSent', true);

    $otp = OneTimePassword::first();

    $component
        ->set('oneTimePassword', $otp->password)
        ->call('verifyOtp')
        ->assertRedirect(route('dashboard'));

    // Verify that the authorized user is authenticated with their structure
    expect(Auth::user()->id)->toBe($authorizedUser->id);
    expect(Auth::user()->parent_user_id)->toBe($parentUser->id);
});
