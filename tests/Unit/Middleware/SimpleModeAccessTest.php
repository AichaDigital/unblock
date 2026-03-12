<?php

declare(strict_types=1);

use App\Http\Middleware\SimpleModeAccess;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

function createRequestWithRoute(string $uri, string $routeName): Request
{
    $request = Request::create($uri, 'GET');
    $route = new Route('GET', $uri, []);
    $route->name($routeName);
    $request->setRouteResolver(fn () => $route);

    return $request;
}

test('unauthenticated user is redirected to login', function () {
    Auth::shouldReceive('check')->andReturn(false);

    $middleware = new SimpleModeAccess;
    $request = createRequestWithRoute('/dashboard', 'dashboard');

    $response = $middleware->handle($request, fn ($req) => response('OK', 200));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe(route('login'));
});

test('simple mode user accessing simple.unblock passes through', function () {
    $user = User::factory()->make(['is_simple_mode_user' => true, 'is_admin' => false]);
    Auth::shouldReceive('check')->andReturn(true);
    Auth::shouldReceive('user')->andReturn($user);

    $middleware = new SimpleModeAccess;
    $request = createRequestWithRoute('/simple-unblock', 'simple.unblock');

    $response = $middleware->handle($request, fn ($req) => response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

test('simple mode user accessing dashboard is redirected to simple.unblock', function () {
    $user = User::factory()->make(['is_simple_mode_user' => true, 'is_admin' => false]);
    Auth::shouldReceive('check')->andReturn(true);
    Auth::shouldReceive('user')->andReturn($user);

    $middleware = new SimpleModeAccess;
    $request = createRequestWithRoute('/dashboard', 'dashboard');

    $response = $middleware->handle($request, fn ($req) => response('OK', 200));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toContain('simple-unblock');
});

test('simple mode user accessing admin area gets 403', function () {
    $user = User::factory()->make(['is_simple_mode_user' => true, 'is_admin' => false]);
    Auth::shouldReceive('check')->andReturn(true);
    Auth::shouldReceive('user')->andReturn($user);

    $middleware = new SimpleModeAccess;
    $request = createRequestWithRoute('/admin/settings', 'admin.settings');

    expect(fn () => $middleware->handle($request, fn ($req) => response('OK', 200)))
        ->toThrow(HttpException::class);
});

test('admin user passes through regardless of is_simple_mode_user', function () {
    $user = User::factory()->make(['is_simple_mode_user' => true, 'is_admin' => true]);
    Auth::shouldReceive('check')->andReturn(true);
    Auth::shouldReceive('user')->andReturn($user);

    $middleware = new SimpleModeAccess;
    $request = createRequestWithRoute('/dashboard', 'dashboard');

    $response = $middleware->handle($request, fn ($req) => response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});

test('regular non-simple user passes through', function () {
    $user = User::factory()->make(['is_simple_mode_user' => false, 'is_admin' => false]);
    Auth::shouldReceive('check')->andReturn(true);
    Auth::shouldReceive('user')->andReturn($user);

    $middleware = new SimpleModeAccess;
    $request = createRequestWithRoute('/dashboard', 'dashboard');

    $response = $middleware->handle($request, fn ($req) => response('OK', 200));

    expect($response->getStatusCode())->toBe(200);
});
