<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ConnectionFailedException;
use App\Mail\{AdminConnectionErrorMail, UserSystemErrorMail};
use App\Models\{Host, User};
use App\Services\FirewallConnectionErrorService;
use Exception;
use Illuminate\Support\Facades\{Log, Mail};
use Mockery;

beforeEach(function () {
    $this->service = new FirewallConnectionErrorService;
});

test('handles connection error with notifications', function () {
    Mail::fake();
    Log::spy();

    $user = User::factory()->create(['email' => 'test@example.com']);
    $host = Host::factory()->create(['fqdn' => 'test.example.com', 'port_ssh' => 22]);

    $ip = '192.168.1.100';
    $errorMessage = 'SSH connection failed';
    $exception = new ConnectionFailedException($errorMessage);

    $this->service->handleConnectionError($ip, $host, $user, $errorMessage, $exception);

    // Verify that the email was sent to an administrator
    Mail::assertSent(AdminConnectionErrorMail::class, function ($mail) use ($ip, $host, $user) {
        return $mail->ip === $ip &&
               $mail->host->id === $host->id &&
               $mail->user->id === $user->id;
    });

    // Verify that the email was sent to the user
    Mail::assertSent(UserSystemErrorMail::class, function ($mail) use ($ip, $host, $user) {
        return $mail->ip === $ip &&
               $mail->host->id === $host->id &&
               $mail->user->id === $user->id;
    });
});

test('identifies critical errors', function () {
    $criticalErrors = [
        new Exception('proc_open function not available'),
        new Exception('error in libcrypto'),
        new Exception('Permission denied (publickey)'),
        new Exception('Connection refused'),
        new Exception('Connection timed out'),
        new Exception('Host key verification failed'),
    ];

    foreach ($criticalErrors as $error) {
        expect($this->service->isCriticalError($error))
            ->toBeTrue("Should identify '{$error->getMessage()}' as critical");
    }

    $nonCriticalError = new Exception('Some other error');
    expect($this->service->isCriticalError($nonCriticalError))->toBeFalse();
});

test('provides diagnostic info', function () {
    $exception = new Exception('error in libcrypto');
    $diagnostics = $this->service->getDiagnosticInfo($exception);

    expect($diagnostics)
        ->toHaveKey('error_type')
        ->toHaveKey('error_message')
        ->toHaveKey('timestamp')
        ->toHaveKey('likely_cause')
        ->toHaveKey('suggested_action')
        ->and($diagnostics['error_type'])->toBe('Exception')
        ->and($diagnostics['error_message'])->toBe('error in libcrypto')
        ->and($diagnostics['likely_cause'])->toContain('SSH key format issue');
});

test('handles mail sending failures gracefully', function () {
    Mail::fake();
    Log::spy(); // Espiar los logs para evitar que se escriban realmente

    $user = User::factory()->create(['email' => 'test@example.com']);
    $host = Host::factory()->create(['fqdn' => 'test.example.com', 'port_ssh' => 22]);

    $ip = '192.168.1.100';
    $errorMessage = 'SSH connection failed';
    $exception = new ConnectionFailedException($errorMessage);

    // Simulate a mail sending failure
    Mail::shouldReceive('to')
        ->andThrow(new Exception('Mail sending failed'));

    // Test that the method doesn't throw an exception even if there are internal problems
    expect(fn () => $this->service->handleConnectionError($ip, $host, $user, $errorMessage, $exception))
        ->not->toThrow(Exception::class);
});

afterEach(function () {
    Mockery::close();
});
