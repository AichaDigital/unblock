<?php

declare(strict_types=1);

use App\Http\Middleware\CheckSimpleModeEnabled;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

test('allows access when simple mode is enabled', function () {
    config()->set('unblock.simple_mode.enabled', true);

    $middleware = new CheckSimpleModeEnabled;
    $request = Request::create('/simple-unblock', 'GET');

    $response = $middleware->handle($request, fn ($req) => response('OK', 200));

    expect($response->status())->toBe(200)
        ->and($response->getContent())->toBe('OK');
});

test('aborts with 404 when simple mode is disabled', function () {
    config()->set('unblock.simple_mode.enabled', false);

    $middleware = new CheckSimpleModeEnabled;
    $request = Request::create('/simple-unblock', 'GET');

    expect(fn () => $middleware->handle($request, fn ($req) => response('OK', 200)))
        ->toThrow(NotFoundHttpException::class);
});

test('defaults to disabled when config is not set', function () {
    config()->set('unblock.simple_mode.enabled', null);

    $middleware = new CheckSimpleModeEnabled;
    $request = Request::create('/simple-unblock', 'GET');

    expect(fn () => $middleware->handle($request, fn ($req) => response('OK', 200)))
        ->toThrow(NotFoundHttpException::class);
});

test('works with different HTTP methods', function () {
    config()->set('unblock.simple_mode.enabled', true);

    $middleware = new CheckSimpleModeEnabled;

    $getRequest = Request::create('/simple-unblock', 'GET');
    $postRequest = Request::create('/simple-unblock', 'POST', ['data' => 'test']);
    $putRequest = Request::create('/simple-unblock', 'PUT');

    $getResponse = $middleware->handle($getRequest, fn ($req) => response('GET OK', 200));
    $postResponse = $middleware->handle($postRequest, fn ($req) => response('POST OK', 200));
    $putResponse = $middleware->handle($putRequest, fn ($req) => response('PUT OK', 200));

    expect($getResponse->status())->toBe(200)
        ->and($postResponse->status())->toBe(200)
        ->and($putResponse->status())->toBe(200);
});
