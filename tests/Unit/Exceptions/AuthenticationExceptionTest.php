<?php

declare(strict_types=1);

use App\Exceptions\AuthenticationException;
use Illuminate\Support\Facades\{Log, Mail};

test('AuthenticationException can be constructed with ip and email', function () {
    $exception = new AuthenticationException(
        ip: '192.168.1.100',
        email: 'user@example.com'
    );

    expect($exception)->toBeInstanceOf(AuthenticationException::class)
        ->and($exception->getMessage())->toBe('Excessive failed authentication attempts from IP');
});

test('AuthenticationException can be constructed with custom message', function () {
    $exception = new AuthenticationException(
        ip: '192.168.1.100',
        email: 'user@example.com',
        message: 'Custom authentication error message'
    );

    expect($exception->getMessage())->toBe('Custom authentication error message');
});

test('AuthenticationException can be constructed with code and previous exception', function () {
    $previous = new Exception('Previous exception');

    $exception = new AuthenticationException(
        ip: '192.168.1.100',
        email: 'user@example.com',
        message: 'Auth failed',
        code: 401,
        previous: $previous
    );

    expect($exception->getCode())->toBe(401)
        ->and($exception->getPrevious())->toBe($previous);
});

test('AuthenticationException stores ip address', function () {
    $exception = new AuthenticationException(
        ip: '192.168.1.100',
        email: 'user@example.com'
    );

    $reflection = new ReflectionClass($exception);
    $ipProperty = $reflection->getProperty('ip');
    $ipProperty->setAccessible(true);

    expect($ipProperty->getValue($exception))->toBe('192.168.1.100');
});

test('AuthenticationException stores email', function () {
    $exception = new AuthenticationException(
        ip: '192.168.1.100',
        email: 'user@example.com'
    );

    $reflection = new ReflectionClass($exception);
    $emailProperty = $reflection->getProperty('email');
    $emailProperty->setAccessible(true);

    expect($emailProperty->getValue($exception))->toBe('user@example.com');
});

test('AuthenticationException report logs error', function () {
    Log::partialMock()
        ->shouldReceive('channel')
        ->with('login_errors')
        ->andReturnSelf()
        ->shouldReceive('error')
        ->once()
        ->with(Mockery::on(function ($message) {
            return str_contains($message, '192.168.1.100')
                && str_contains($message, 'user@example.com')
                && str_contains($message, 'Excessive failed authentication attempts');
        }));

    Mail::fake();

    $exception = new AuthenticationException(
        ip: '192.168.1.100',
        email: 'user@example.com'
    );

    $exception->report();
});

test('AuthenticationException extends Exception', function () {
    $exception = new AuthenticationException(
        ip: '192.168.1.100',
        email: 'user@example.com'
    );

    expect($exception)->toBeInstanceOf(Exception::class);
});

test('AuthenticationException is throwable', function () {
    expect(function () {
        throw new AuthenticationException(
            ip: '192.168.1.100',
            email: 'user@example.com'
        );
    })->toThrow(AuthenticationException::class);
});
