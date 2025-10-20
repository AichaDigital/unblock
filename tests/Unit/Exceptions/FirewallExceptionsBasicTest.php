<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\{
    CommandExecutionException,
    ConnectionFailedException,
    CsfServiceException,
    FirewallException,
    InvalidIpException
};
use Illuminate\Support\Facades\Config;
use ReflectionMethod;
use Tests\FirewallTestConstants as TC;

test('FirewallException stores properties correctly', function () {
    $message = 'Test error message';
    $hostName = TC::TEST_HOST_FQDN;
    $ipAddress = TC::TEST_BLOCKED_IP;
    $context = ['key' => 'value'];

    $exception = new FirewallException($message, $hostName, $ipAddress, $context);

    expect($exception)
        ->getMessage()->toBe($message)
        ->getHostName()->toBe($hostName)
        ->getIpAddress()->toBe($ipAddress)
        ->getContext()->toBe($context);
});

test('CommandExecutionException stores command information', function () {
    $command = 'csf -g '.TC::TEST_BLOCKED_IP;
    $output = 'Command output';
    $errorOutput = 'Error output';
    $message = 'Command failed';

    $exception = new CommandExecutionException(
        $command,
        $output,
        $errorOutput,
        $message
    );

    expect($exception)
        ->getMessage()->toBe($message)
        ->getCommand()->toBe($command)
        ->getOutput()->toBe($output)
        ->getErrorOutput()->toBe($errorOutput);
});

test('InvalidIpException stores IP address and provides validation message', function () {
    $invalidIp = '999.999.999.999';
    $exception = new InvalidIpException($invalidIp);

    expect($exception)
        ->getIpAddress()->toBe($invalidIp)
        ->getValidationErrorDescription()
        ->toContain($invalidIp)
        ->toContain('no tiene un formato válido');
});

test('ConnectionFailedException stores and reports attempts correctly', function () {
    $attempts = 3;
    $exception = new ConnectionFailedException(
        'Connection failed',
        TC::TEST_HOST_FQDN,
        $attempts,
        TC::TEST_BLOCKED_IP
    );

    expect($exception)
        ->getMessage()->toBe('Connection failed')
        ->getHostName()->toBe(TC::TEST_HOST_FQDN)
        ->getIpAddress()->toBe(TC::TEST_BLOCKED_IP)
        ->getAttempts()->toBe($attempts);
});

test('CsfServiceException stores operation type and handles critical failures', function () {
    Config::set('unblock.critical_hosts', [TC::TEST_HOST_FQDN]);

    $operationType = 'unblock';
    $exception = new CsfServiceException(
        $operationType,
        'CSF service error',
        TC::TEST_HOST_FQDN,
        TC::TEST_BLOCKED_IP
    );

    expect($exception)
        ->getOperationType()->toBe($operationType)
        ->getHostName()->toBe(TC::TEST_HOST_FQDN)
        ->getIpAddress()->toBe(TC::TEST_BLOCKED_IP);

    // Test protected method through reflection
    $reflectionMethod = new ReflectionMethod($exception, 'isCriticalFailure');

    expect($reflectionMethod->invoke($exception))->toBeTrue();
});
