<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyIsAdminMiddleware;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

test('unauthenticated user is redirected to login route', function () {
    Auth::shouldReceive('check')->andReturn(false);

    $middleware = new VerifyIsAdminMiddleware;
    $request = Request::create('/admin/dashboard', 'GET');

    $response = $middleware->handle($request, fn ($req) => response('OK', 200));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe(route('login'));
});

test('admin user passes through', function () {
    $user = User::factory()->admin()->make();
    Auth::shouldReceive('check')->andReturn(true);
    Auth::shouldReceive('user')->andReturn($user);

    $middleware = new VerifyIsAdminMiddleware;
    $request = Request::create('/admin/dashboard', 'GET');

    $response = $middleware->handle($request, fn ($req) => response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

test('non-admin user is redirected to dashboard', function () {
    $user = User::factory()->make(['is_admin' => false]);
    Auth::shouldReceive('check')->andReturn(true);
    Auth::shouldReceive('user')->andReturn($user);

    $middleware = new VerifyIsAdminMiddleware;
    $request = Request::create('/admin/dashboard', 'GET');

    $response = $middleware->handle($request, fn ($req) => response('OK', 200));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe(route('dashboard'));
});

test('trashed admin user is redirected to dashboard', function () {
    $user = User::factory()->admin()->create();
    $user->delete(); // Soft delete

    Auth::shouldReceive('check')->andReturn(true);
    Auth::shouldReceive('user')->andReturn($user);

    $middleware = new VerifyIsAdminMiddleware;
    $request = Request::create('/admin/dashboard', 'GET');

    $response = $middleware->handle($request, fn ($req) => response('OK', 200));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe(route('dashboard'));
});
