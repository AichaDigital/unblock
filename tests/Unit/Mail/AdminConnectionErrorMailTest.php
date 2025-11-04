<?php

declare(strict_types=1);

use App\Mail\AdminConnectionErrorMail;
use App\Models\{Host, User};

test('admin connection error mail has correct envelope', function () {
    $user = User::factory()->create();
    $host = Host::factory()->create();
    $exception = new Exception('SSH connection failed');

    $mail = new AdminConnectionErrorMail(
        ip: '192.168.1.1',
        host: $host,
        user: $user,
        errorMessage: 'Connection timeout',
        exception: $exception
    );

    $envelope = $mail->envelope();

    expect($envelope->subject)->toBe('[CRÍTICO] Error de Conexión SSH - Sistema Firewall');
});

test('admin connection error mail has correct content view', function () {
    $user = User::factory()->create();
    $host = Host::factory()->create();
    $exception = new Exception('SSH connection failed');

    $mail = new AdminConnectionErrorMail(
        ip: '10.0.0.1',
        host: $host,
        user: $user,
        errorMessage: 'Network unreachable',
        exception: $exception
    );

    $content = $mail->content();

    expect($content->view)->toBe('emails.admin.connection-error');
});

test('admin connection error mail has no attachments', function () {
    $user = User::factory()->create();
    $host = Host::factory()->create();
    $exception = new Exception('Authentication failed');

    $mail = new AdminConnectionErrorMail(
        ip: '172.16.0.1',
        host: $host,
        user: $user,
        errorMessage: 'Invalid credentials',
        exception: $exception
    );

    $attachments = $mail->attachments();

    expect($attachments)->toBeArray()
        ->and($attachments)->toBeEmpty();
});

test('admin connection error mail stores all constructor data', function () {
    $user = User::factory()->create();
    $host = Host::factory()->create();
    $exception = new Exception('Test exception message');
    $ip = '203.0.113.1';
    $errorMessage = 'Custom error message';

    $mail = new AdminConnectionErrorMail(
        ip: $ip,
        host: $host,
        user: $user,
        errorMessage: $errorMessage,
        exception: $exception
    );

    expect($mail->ip)->toBe($ip)
        ->and($mail->host->id)->toBe($host->id)
        ->and($mail->user->id)->toBe($user->id)
        ->and($mail->errorMessage)->toBe($errorMessage)
        ->and($mail->exception->getMessage())->toBe('Test exception message');
});
