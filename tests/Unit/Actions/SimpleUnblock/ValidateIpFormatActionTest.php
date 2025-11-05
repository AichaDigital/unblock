<?php

declare(strict_types=1);

use App\Actions\SimpleUnblock\ValidateIpFormatAction;
use App\Exceptions\InvalidIpException;

test('action validates valid IPv4 addresses', function () {
    $validIps = [
        '192.168.1.1',
        '10.0.0.1',
        '172.16.0.1',
        '8.8.8.8',
        '255.255.255.255',
        '0.0.0.0',
    ];

    foreach ($validIps as $ip) {
        expect(fn () => ValidateIpFormatAction::run($ip))->not->toThrow(InvalidIpException::class);
    }
});

test('action validates valid IPv6 addresses', function () {
    $validIps = [
        '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
        '2001:db8::1',
        '::1',
        'fe80::1',
        '::',
    ];

    foreach ($validIps as $ip) {
        expect(fn () => ValidateIpFormatAction::run($ip))->not->toThrow(InvalidIpException::class);
    }
});

test('action throws exception for invalid IPv4 format', function () {
    ValidateIpFormatAction::run('256.256.256.256');
})->throws(InvalidIpException::class);

test('action throws exception for malformed IP', function () {
    ValidateIpFormatAction::run('not-an-ip');
})->throws(InvalidIpException::class);

test('action throws exception for empty string', function () {
    ValidateIpFormatAction::run('');
})->throws(InvalidIpException::class);

test('action throws exception for partial IP', function () {
    ValidateIpFormatAction::run('192.168.1');
})->throws(InvalidIpException::class);

test('action throws exception for IP with invalid characters', function () {
    ValidateIpFormatAction::run('192.168.1.1a');
})->throws(InvalidIpException::class);

test('action returns void when validation passes', function () {
    $result = ValidateIpFormatAction::run('192.168.1.1');

    expect($result)->toBeNull();
});

test('action exception message is provided', function () {
    try {
        ValidateIpFormatAction::run('invalid-ip');
        $this->fail('Exception was not thrown');
    } catch (InvalidIpException $e) {
        expect($e->getMessage())->not->toBeEmpty();
    }
});
