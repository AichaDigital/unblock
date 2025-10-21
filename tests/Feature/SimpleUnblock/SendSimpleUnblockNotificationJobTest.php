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

test('notification job sends email to user on success', function () {
    $report = Report::factory()->anonymous()->create([
        'ip' => '192.168.1.1',
        'host_id' => $this->host->id,
    ]);

    $job = new SendSimpleUnblockNotificationJob(
        reportId: (string) $report->id,
        email: $this->email,
        domain: $this->domain,
        adminOnly: false
    );

    $job->handle();

    Mail::assertSent(SimpleUnblockNotificationMail::class, function ($mail) {
        return $mail->hasTo($this->email)
            && $mail->isSuccess === true
            && $mail->isAdminCopy === false;
    });
});

test('notification job sends email to admin on success', function () {
    $report = Report::factory()->anonymous()->create([
        'ip' => '192.168.1.1',
        'host_id' => $this->host->id,
    ]);

    $job = new SendSimpleUnblockNotificationJob(
        reportId: (string) $report->id,
        email: $this->email,
        domain: $this->domain,
        adminOnly: false
    );

    $job->handle();

    Mail::assertSent(SimpleUnblockNotificationMail::class, function ($mail) {
        return $mail->hasTo(config('unblock.admin_email'))
            && $mail->isSuccess === true
            && $mail->isAdminCopy === true;
    });
});

test('notification job does not duplicate admin email', function () {
    $adminEmail = config('unblock.admin_email');

    $report = Report::factory()->anonymous()->create([
        'ip' => '192.168.1.1',
        'host_id' => $this->host->id,
    ]);

    $job = new SendSimpleUnblockNotificationJob(
        reportId: (string) $report->id,
        email: $adminEmail, // Same as admin email
        domain: $this->domain,
        adminOnly: false
    );

    $job->handle();

    // Should only send once (not to both user and admin separately)
    Mail::assertSent(SimpleUnblockNotificationMail::class, 1);
});

test('notification job sends admin-only notification on no match', function () {
    $job = new SendSimpleUnblockNotificationJob(
        reportId: null,
        email: $this->email,
        domain: $this->domain,
        adminOnly: true,
        reason: 'no_match_found'
    );

    $job->handle();

    Mail::assertSent(SimpleUnblockNotificationMail::class, function ($mail) {
        return $mail->hasTo(config('unblock.admin_email'))
            && $mail->isSuccess === false
            && $mail->reason === 'no_match_found';
    });

    // Should NOT send to user
    Mail::assertNotSent(SimpleUnblockNotificationMail::class, fn ($mail) => $mail->hasTo($this->email));
});

test('notification job handles missing report gracefully', function () {
    $job = new SendSimpleUnblockNotificationJob(
        reportId: '99999',
        email: $this->email,
        domain: $this->domain,
        adminOnly: false
    );

    $job->handle();

    Mail::assertNothingSent();
});

test('notification job handles missing admin email gracefully', function () {
    Config::set('unblock.admin_email', null);

    $job = new SendSimpleUnblockNotificationJob(
        reportId: null,
        email: $this->email,
        domain: $this->domain,
        adminOnly: true,
        reason: 'no_match_found'
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
            adminOnly: true,
            reason: $reason
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
        adminOnly: true,
        reason: 'ip_blocked_but_domain_not_found',
        analysisData: $analysisData
    );

    $job->handle();

    Mail::assertSent(SimpleUnblockNotificationMail::class, function ($mail) use ($analysisData) {
        return $mail->analysisData === $analysisData;
    });
});
