<?php

declare(strict_types=1);

use App\Livewire\AdminOtpVerification;
use App\Models\User;
use Illuminate\Support\Facades\{Log, Notification};
use Livewire\Livewire;

beforeEach(function () {
    config()->set('unblock.admin_otp.enabled', true);
    config()->set('unblock.simple_mode.enabled', false);
    Log::spy();
});

// ============================================================================
// OTP Send Failure Detection in mount()
// ============================================================================

test('mount detects otp_send_failed flash and shows SMTP error message', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    session()->put('admin_otp_pending_user_id', $admin->id);
    session()->put('last_activity', now()->timestamp);
    session()->flash('otp_send_failed', true);
    session()->flash('otp_error_hint', 'smtp_connection');

    Livewire::test(AdminOtpVerification::class)
        ->assertSet('sendFailed', true)
        ->assertSet('errorHint', 'smtp_connection')
        ->assertSet('messageType', 'error')
        ->assertSet('canResend', true)
        ->assertSee(__('admin_otp.send_failed_smtp'));
});

test('mount detects otp_send_failed flash and shows generic error message', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    session()->put('admin_otp_pending_user_id', $admin->id);
    session()->put('last_activity', now()->timestamp);
    session()->flash('otp_send_failed', true);
    session()->flash('otp_error_hint', 'generic');

    Livewire::test(AdminOtpVerification::class)
        ->assertSet('sendFailed', true)
        ->assertSet('errorHint', 'generic')
        ->assertSet('messageType', 'error')
        ->assertSet('canResend', true)
        ->assertSee(__('admin_otp.send_failed_generic'));
});

test('mount defaults to generic hint when otp_error_hint is missing', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    session()->put('admin_otp_pending_user_id', $admin->id);
    session()->put('last_activity', now()->timestamp);
    session()->flash('otp_send_failed', true);
    // No otp_error_hint flash

    Livewire::test(AdminOtpVerification::class)
        ->assertSet('sendFailed', true)
        ->assertSet('errorHint', 'generic')
        ->assertSet('messageType', 'error')
        ->assertSet('canResend', true);
});

test('mount shows success message when otp_sent flash is present', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    session()->put('admin_otp_pending_user_id', $admin->id);
    session()->put('admin_otp_sent_at', now()->subMinutes(2)->timestamp);
    session()->put('last_activity', now()->timestamp);
    session()->flash('otp_sent', true);

    Livewire::test(AdminOtpVerification::class)
        ->assertSet('sendFailed', false)
        ->assertSet('messageType', 'success')
        ->assertSee(__('admin_otp.otp_sent'));
});

test('mount does not show send failure when otp_sent flash takes precedence', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    session()->put('admin_otp_pending_user_id', $admin->id);
    session()->put('admin_otp_sent_at', now()->subMinutes(2)->timestamp);
    session()->put('last_activity', now()->timestamp);
    // Only otp_sent, no failure
    session()->flash('otp_sent', true);

    Livewire::test(AdminOtpVerification::class)
        ->assertSet('sendFailed', false)
        ->assertSet('errorHint', '');
});

// ============================================================================
// Resend success clears sendFailed state
// ============================================================================

test('successful resend clears sendFailed state', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    session()->put('admin_otp_pending_user_id', $admin->id);
    session()->put('admin_otp_sent_at', now()->subMinutes(2)->timestamp);
    session()->put('last_activity', now()->timestamp);

    Livewire::test(AdminOtpVerification::class)
        ->set('sendFailed', true)
        ->set('errorHint', 'smtp_connection')
        ->call('resend')
        ->assertSet('sendFailed', false)
        ->assertSet('errorHint', '')
        ->assertSet('messageType', 'success')
        ->assertSee(__('admin_otp.otp_resent'));
});

// ============================================================================
// View rendering with sendFailed state
// ============================================================================

test('view shows warning alert when sendFailed is true', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    session()->put('admin_otp_pending_user_id', $admin->id);
    session()->put('last_activity', now()->timestamp);
    session()->flash('otp_send_failed', true);
    session()->flash('otp_error_hint', 'smtp_connection');

    Livewire::test(AdminOtpVerification::class)
        ->assertSee('SMTP');
});

test('view does not show warning alert when sendFailed is false', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    session()->put('admin_otp_pending_user_id', $admin->id);
    session()->put('admin_otp_sent_at', now()->subSeconds(30)->timestamp);
    session()->put('last_activity', now()->timestamp);
    session()->flash('otp_sent', true);

    Livewire::test(AdminOtpVerification::class)
        ->assertDontSee('SMTP');
});
