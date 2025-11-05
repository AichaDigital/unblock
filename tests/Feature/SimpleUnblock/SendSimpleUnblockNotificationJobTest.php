<?php

declare(strict_types=1);

use App\Jobs\SendSimpleUnblockNotificationJob;
use App\Mail\SimpleUnblockNotificationMail;
use App\Models\{Host, Report};
use App\Services\AnonymousUserService;
use Illuminate\Support\Facades\{Config, Mail};

beforeEach(function () {
    Mail::fake();
    Config::set('unblock.admin_email', 'admin@example.com');

    // Clear anonymous user cache to ensure fresh user for each test
    AnonymousUserService::clearCache();

    // Ensure anonymous user exists for tests
    AnonymousUserService::get();

    $this->host = Host::factory()->create();
    $this->email = 'user@example.com';
    $this->domain = 'example.com';
});

test('notification sends SUCCESS email when report indicates unblock', function () {
    $report = Report::factory()->anonymous()->create([
        'was_unblocked' => true, // IP was blocked and unblocked
        'analysis' => [
            'was_blocked' => true, // Explicitly: IP was blocked
            'csf' => ['blocked' => true],
            'logs' => ['some logs'],
        ],
    ]);

    $job = new SendSimpleUnblockNotificationJob(
        reportId: (string) $report->id,
        email: $this->email,
        domain: $this->domain,
    );

    $job->handle();

    // 1. Check user email
    Mail::assertSent(SimpleUnblockNotificationMail::class, function ($mail) {
        return $mail->hasTo($this->email)
            && $mail->isSuccess === true // Analysis completed successfully
            && $mail->wasBlocked === true // IP was blocked
            && ! $mail->isAdminCopy;
    });

    // 2. Check admin copy
    Mail::assertSent(SimpleUnblockNotificationMail::class, function ($mail) {
        return $mail->hasTo(config('unblock.admin_email'))
            && $mail->isSuccess === true // Analysis completed successfully
            && $mail->wasBlocked === true // IP was blocked
            && $mail->isAdminCopy;
    });
});

test('notification sends INFO email when report indicates NO unblock', function () {
    $report = Report::factory()->anonymous()->create([
        'was_unblocked' => false, // IP was NOT blocked
        'analysis' => [
            'was_blocked' => false, // Explicitly: IP was not blocked
            'csf' => [],
            'logs' => [],
        ],
    ]);

    $job = new SendSimpleUnblockNotificationJob(
        reportId: (string) $report->id,
        email: $this->email,
        domain: $this->domain,
    );

    $job->handle();

    // 1. Check user email (isSuccess=true because analysis completed, wasBlocked=false)
    Mail::assertSent(SimpleUnblockNotificationMail::class, function ($mail) {
        return $mail->hasTo($this->email)
            && $mail->isSuccess === true // Analysis completed successfully
            && $mail->wasBlocked === false // But IP was NOT blocked
            && ! $mail->isAdminCopy;
    });

    // 2. Check admin copy
    Mail::assertSent(SimpleUnblockNotificationMail::class, function ($mail) {
        return $mail->hasTo(config('unblock.admin_email'))
            && $mail->isSuccess === true // Analysis completed successfully
            && $mail->wasBlocked === false // But IP was NOT blocked
            && $mail->isAdminCopy;
    });
});

test('notification job does not duplicate admin email', function () {
    $adminEmail = config('unblock.admin_email');

    $report = Report::factory()->anonymous()->create([
        'was_unblocked' => true,
    ]);

    $job = new SendSimpleUnblockNotificationJob(
        reportId: (string) $report->id,
        email: $adminEmail, // User email is the same as admin email
        domain: $this->domain,
    );

    $job->handle();

    // Should only send once to the admin address.
    Mail::assertSent(SimpleUnblockNotificationMail::class, 1);
    Mail::assertSent(SimpleUnblockNotificationMail::class, function ($mail) use ($adminEmail) {
        return $mail->hasTo($adminEmail);
    });
});

test('notification job sends admin-only ALERT on no match (suspicious activity)', function () {
    $job = new SendSimpleUnblockNotificationJob(
        reportId: null,
        email: $this->email,
        domain: $this->domain,
        reason: 'no_match_found',
        adminOnly: true // This simulates a call from a pre-check failure, like `handleSuspiciousAttempt`
    );

    $job->handle();

    Mail::assertSent(SimpleUnblockNotificationMail::class, function ($mail) {
        return $mail->hasTo(config('unblock.admin_email'))
            && $mail->isSuccess === false
            && $mail->reason === 'no_match_found';
    });

    // Should NOT send to user in this specific alert case
    Mail::assertNotSent(SimpleUnblockNotificationMail::class, fn ($mail) => $mail->hasTo($this->email));
});

test('notification job sends admin-only ALERT for missing report', function () {
    $job = new SendSimpleUnblockNotificationJob(
        reportId: '99999', // A non-existent report ID
        email: $this->email,
        domain: $this->domain,
    );

    $job->handle();

    // Should send an admin alert, not nothing.
    Mail::assertSent(SimpleUnblockNotificationMail::class, function ($mail) {
        return $mail->hasTo(config('unblock.admin_email'))
            && $mail->isSuccess === false
            && $mail->reason === 'report_not_found';
    });
    // Should NOT send to the user.
    Mail::assertNotSent(SimpleUnblockNotificationMail::class, fn ($mail) => $mail->hasTo($this->email));
});

test('notification job handles missing admin email gracefully', function () {
    Config::set('unblock.admin_email', null);

    $job = new SendSimpleUnblockNotificationJob(
        reportId: null,
        email: $this->email,
        domain: $this->domain,
        reason: 'no_match_found',
        adminOnly: true
    );

    $job->handle();

    Mail::assertNothingSent();
});

test('notification job includes reason in admin alert', function () {
    $reasons = [
        'ip_blocked_but_domain_not_found',
        'domain_found_but_ip_not_blocked',
        'no_match_found',
        'job_failure',
    ];

    foreach ($reasons as $reason) {
        Mail::fake();

        $job = new SendSimpleUnblockNotificationJob(
            reportId: null,
            email: $this->email,
            domain: $this->domain,
            reason: $reason,
            adminOnly: true
        );

        $job->handle();

        Mail::assertSent(SimpleUnblockNotificationMail::class, function ($mail) use ($reason) {
            return $mail->reason === $reason;
        });
    }
});

test('notification job includes analysis data in admin alert', function () {
    $analysisData = [
        'ip' => '192.168.1.1',
        'was_blocked' => true,
        'logs_preview' => 'Some log data...',
    ];

    $job = new SendSimpleUnblockNotificationJob(
        reportId: null,
        email: $this->email,
        domain: $this->domain,
        reason: 'ip_blocked_but_domain_not_found',
        analysisData: $analysisData,
        adminOnly: true
    );

    $job->handle();

    Mail::assertSent(SimpleUnblockNotificationMail::class, function ($mail) use ($analysisData) {
        return $mail->analysisData === $analysisData;
    });
});
