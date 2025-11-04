<?php

declare(strict_types=1);

use App\Mail\SimpleUnblockNotificationMail;
use App\Models\{Host, Report, User};

test('simple unblock notification mail success has correct envelope for user', function () {
    $user = User::factory()->create();
    $host = Host::factory()->create();
    $report = Report::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'was_unblocked' => true,
    ]);

    $mail = new SimpleUnblockNotificationMail(
        email: 'user@example.com',
        domain: 'example.com',
        report: $report,
        isSuccess: true,
        isAdminCopy: false
    );

    $envelope = $mail->envelope();

    expect($envelope->subject)->toContain('example.com')
        ->and($envelope->subject)->not->toContain('[ADMIN]');
});

test('simple unblock notification mail success has correct envelope for admin', function () {
    $user = User::factory()->create();
    $host = Host::factory()->create();
    $report = Report::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'was_unblocked' => true,
    ]);

    $mail = new SimpleUnblockNotificationMail(
        email: 'user@example.com',
        domain: 'example.com',
        report: $report,
        isSuccess: true,
        isAdminCopy: true
    );

    $envelope = $mail->envelope();

    expect($envelope->subject)->toContain('[ADMIN]')
        ->and($envelope->subject)->toContain('example.com');
});

test('simple unblock notification mail failure has admin alert subject', function () {
    $mail = new SimpleUnblockNotificationMail(
        email: 'user@test.com',
        domain: 'test.com',
        report: null,
        isSuccess: false,
        isAdminCopy: true,
        reason: 'Suspicious activity detected'
    );

    $envelope = $mail->envelope();

    expect($envelope->subject)->toContain('test.com');
});

test('simple unblock notification mail success uses correct view', function () {
    $user = User::factory()->create();
    $host = Host::factory()->create();
    $report = Report::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
    ]);

    $mail = new SimpleUnblockNotificationMail(
        email: 'user@domain.com',
        domain: 'domain.com',
        report: $report,
        isSuccess: true,
        isAdminCopy: false
    );

    $content = $mail->content();

    expect($content->view)->toBe('emails.simple-unblock-success');
});

test('simple unblock notification mail failure uses admin alert view', function () {
    $mail = new SimpleUnblockNotificationMail(
        email: 'user@domain.com',
        domain: 'domain.com',
        report: null,
        isSuccess: false,
        isAdminCopy: true
    );

    $content = $mail->content();

    expect($content->view)->toBe('emails.simple-unblock-admin-alert');
});

test('simple unblock notification mail includes all data in content', function () {
    $user = User::factory()->create();
    $host = Host::factory()->create();
    $report = Report::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
    ]);

    $analysisData = ['was_blocked' => true, 'domain' => 'test.com'];

    $mail = new SimpleUnblockNotificationMail(
        email: 'user@test.com',
        domain: 'test.com',
        report: $report,
        isSuccess: true,
        isAdminCopy: false,
        reason: 'Test reason',
        hostFqdn: 'host.example.com',
        analysisData: $analysisData
    );

    $content = $mail->content();

    expect($content->with)->toHaveKey('email', 'user@test.com')
        ->and($content->with)->toHaveKey('domain', 'test.com')
        ->and($content->with)->toHaveKey('report')
        ->and($content->with['report']->id)->toBe($report->id)
        ->and($content->with)->toHaveKey('isAdminCopy', false)
        ->and($content->with)->toHaveKey('reason', 'Test reason')
        ->and($content->with)->toHaveKey('hostFqdn', 'host.example.com')
        ->and($content->with)->toHaveKey('analysisData')
        ->and($content->with['analysisData'])->toBe($analysisData);
});

test('simple unblock notification mail sets locale on construction', function () {
    $originalLocale = app()->getLocale();
    config()->set('app.locale', 'es');

    $mail = new SimpleUnblockNotificationMail(
        email: 'user@test.com',
        domain: 'test.com',
        report: null,
        isSuccess: true,
        isAdminCopy: false
    );

    expect(app()->getLocale())->toBe('es');

    // Restore
    app()->setLocale($originalLocale);
});

test('simple unblock notification mail handles null optional parameters', function () {
    $mail = new SimpleUnblockNotificationMail(
        email: 'user@test.com',
        domain: 'test.com',
        report: null,
        isSuccess: false,
        isAdminCopy: true
    );

    expect($mail->report)->toBeNull()
        ->and($mail->reason)->toBeNull()
        ->and($mail->hostFqdn)->toBeNull()
        ->and($mail->analysisData)->toBeNull();
});
