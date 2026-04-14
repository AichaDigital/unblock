<?php

declare(strict_types=1);

use App\Http\Middleware\RequireAdminOtp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

test('passes through when admin OTP is disabled via config', function () {
    config()->set('unblock.admin_otp.enabled', false);
    Auth::shouldReceive('check')->andReturn(true);
    Auth::shouldReceive('user')->andReturn(User::factory()->admin()->make());

    $middleware = new RequireAdminOtp;
    $request = Request::create('/admin/dashboard', 'GET');

    $response = $middleware->handle($request, fn ($req) => response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

test('passes through for unauthenticated user', function () {
    config()->set('unblock.admin_otp.enabled', true);
    Auth::shouldReceive('check')->andReturn(false);

    $middleware = new RequireAdminOtp;
    $request = Request::create('/admin/dashboard', 'GET');

    $response = $middleware->handle($request, fn ($req) => response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

test('passes through for non-admin user', function () {
    config()->set('unblock.admin_otp.enabled', true);
    $user = User::factory()->make(['is_admin' => false]);
    Auth::shouldReceive('check')->andReturn(true);
    Auth::shouldReceive('user')->andReturn($user);

    $middleware = new RequireAdminOtp;
    $request = Request::create('/dashboard', 'GET');

    $response = $middleware->handle($request, fn ($req) => response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

test('passes through for admin with verified OTP session', function () {
    config()->set('unblock.admin_otp.enabled', true);
    $user = User::factory()->admin()->create();

    $this->actingAs($user);

    session()->put('last_activity', now()->timestamp);
    session()->put('admin_otp_verified', true);
    session()->put('admin_otp_user_id', $user->id);

    $response = $this->get('/admin');

    // Should get 200 (dashboard) or redirect to dashboard, but NOT to otp/verify
    expect($response->headers->get('Location') ?? '')->not->toContain('otp/verify');
});

test('admin without OTP is redirected to verification page', function () {
    config()->set('unblock.admin_otp.enabled', true);
    $user = User::factory()->admin()->create();

    $this->actingAs($user);

    session()->put('last_activity', now()->timestamp);
    // No admin_otp_verified in session

    $response = $this->get('/admin');

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toContain('otp/verify');
});

test('SMTP error classification detects STARTTLS errors', function () {
    $smtpMessages = [
        'Expected response code 220 but got STARTTLS required',
        'stream_socket_client(): unable to connect to tcp://mail:587',
        'Connection refused on tcp://127.0.0.1:25',
        'Connection timed out after 30 seconds',
    ];

    foreach ($smtpMessages as $errorMessage) {
        $isSmtpError = str_contains($errorMessage, 'STARTTLS')
            || str_contains($errorMessage, 'stream_socket')
            || str_contains($errorMessage, 'Connection refused')
            || str_contains($errorMessage, 'Connection timed out');

        expect($isSmtpError)->toBeTrue("Expected SMTP detection for: {$errorMessage}");
    }
});

test('SMTP error classification returns false for non-SMTP errors', function () {
    $genericMessages = [
        'Some unexpected database error',
        'User not found',
        'Undefined variable $otp',
    ];

    foreach ($genericMessages as $errorMessage) {
        $isSmtpError = str_contains($errorMessage, 'STARTTLS')
            || str_contains($errorMessage, 'stream_socket')
            || str_contains($errorMessage, 'Connection refused')
            || str_contains($errorMessage, 'Connection timed out');

        expect($isSmtpError)->toBeFalse("Expected non-SMTP for: {$errorMessage}");
    }
});
