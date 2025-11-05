<?php

declare(strict_types=1);

use App\Exceptions\InvalidKeyException;
use Illuminate\Support\Facades\Log;

test('InvalidKeyException can be constructed with message', function () {
    $exception = new InvalidKeyException('SSH key is invalid');

    expect($exception)->toBeInstanceOf(InvalidKeyException::class)
        ->and($exception->getMessage())->toBe('SSH key is invalid');
});

test('InvalidKeyException can be constructed with code', function () {
    $exception = new InvalidKeyException('SSH key is invalid', 401);

    expect($exception->getCode())->toBe(401);
});

test('InvalidKeyException can be constructed with previous exception', function () {
    $previous = new Exception('Previous error');
    $exception = new InvalidKeyException('SSH key is invalid', 0, $previous);

    expect($exception->getPrevious())->toBe($previous);
});

test('InvalidKeyException extends Exception', function () {
    $exception = new InvalidKeyException('SSH key is invalid');

    expect($exception)->toBeInstanceOf(Exception::class);
});

test('InvalidKeyException is throwable', function () {
    expect(function () {
        throw new InvalidKeyException('SSH key is invalid');
    })->toThrow(InvalidKeyException::class);
});

test('InvalidKeyException report logs the error message', function () {
    Log::spy();

    $exception = new InvalidKeyException('SSH key format is incorrect');

    $exception->report();

    Log::shouldHaveReceived('error')
        ->once()
        ->with('SSH key format is incorrect');
});

test('InvalidKeyException report logs empty message', function () {
    Log::spy();

    $exception = new InvalidKeyException('');

    $exception->report();

    Log::shouldHaveReceived('error')
        ->once()
        ->with('');
});
