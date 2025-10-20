<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\InvalidKeyException;
use App\Models\Host;
use App\Services\FirewallService;
use Illuminate\Support\Facades\Storage;
use Mockery;
use ReflectionMethod;
use Tests\FirewallTestConstants as TC;

beforeEach(function () {
    $this->firewallService = new FirewallService;
});

test('it generates ssh key', function () {
    Storage::fake('ssh');

    $hash = 'test-hash-content';
    $key = $this->firewallService->generateSshKey($hash);

    expect(basename($key))->toContain('key_')
        ->and(Storage::disk('ssh')->exists(basename($key)))->toBeTrue()
        ->and(trim(Storage::disk('ssh')->get(basename($key))))->toBe($hash);
});

test('it escapes ip for grep', function () {
    $ip = '192.168.1.1';
    $method = new ReflectionMethod(FirewallService::class, 'escapeIpForGrep');

    $escapedIp = $method->invoke($this->firewallService, $ip);

    expect($escapedIp)->toBe('192\.168\.1\.1');
});

test('it adds error if string starts with error', function () {
    $method = new ReflectionMethod(FirewallService::class, 'addErrorIfStartsWithError');

    $errorMessage = 'Error: Something went wrong';
    $key = 'test_key';

    $method->invoke($this->firewallService, $errorMessage, $key);

    $errors = $this->firewallService->getErrors();
    expect($errors)->toHaveKey($key)
        ->and($errors[$key])->toBe($errorMessage);
});

test('it sets and gets data', function () {
    $key = 'test_key';
    $value = 'test_value';

    $this->firewallService->setData($key, $value);

    $data = $this->firewallService->getData();

    expect($data)->toHaveKey($key)
        ->and($data[$key])->toBe($value);
});

test('it throws exception for invalid unity', function () {
    $host = Mockery::mock(Host::class);
    $host->shouldReceive('getAttribute')->andReturn('test', 'test');

    expect(fn () => $this->firewallService->checkProblems($host, TC::TEST_SSH_KEY, 'invalid_unity', '192.168.1.1'))
        ->toThrow(InvalidKeyException::class);
});
