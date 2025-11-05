<?php

declare(strict_types=1);

use App\Mail\UserSystemErrorMail;
use App\Models\{Host, User};

test('user system error mail has correct envelope', function () {
    $user = User::factory()->create();
    $host = Host::factory()->create();

    $mail = new UserSystemErrorMail(
        ip: '192.168.1.1',
        host: $host,
        user: $user
    );

    $envelope = $mail->envelope();

    expect($envelope->subject)->toBe('Error Temporal del Sistema - Verificación de Firewall');
});

test('user system error mail has correct content view', function () {
    $user = User::factory()->create();
    $host = Host::factory()->create();

    $mail = new UserSystemErrorMail(
        ip: '10.0.0.1',
        host: $host,
        user: $user
    );

    $content = $mail->content();

    expect($content->view)->toBe('emails.user.system-error');
});

test('user system error mail has no attachments', function () {
    $user = User::factory()->create();
    $host = Host::factory()->create();

    $mail = new UserSystemErrorMail(
        ip: '172.16.0.1',
        host: $host,
        user: $user
    );

    $attachments = $mail->attachments();

    expect($attachments)->toBeArray()
        ->and($attachments)->toBeEmpty();
});

test('user system error mail stores all constructor data', function () {
    $user = User::factory()->create();
    $host = Host::factory()->create();
    $ip = '203.0.113.1';

    $mail = new UserSystemErrorMail(
        ip: $ip,
        host: $host,
        user: $user
    );

    expect($mail->ip)->toBe($ip)
        ->and($mail->host->id)->toBe($host->id)
        ->and($mail->user->id)->toBe($user->id);
});
