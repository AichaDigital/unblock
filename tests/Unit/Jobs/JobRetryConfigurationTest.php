<?php

declare(strict_types=1);

use App\Jobs\{
    ProcessFirewallCheckJob,
    ProcessHqWhitelistJob,
    ProcessSimpleUnblockJob,
    RemoveExpiredBfmWhitelistIps,
    SendReportNotificationJob,
    SendSimpleUnblockNotificationJob
};
use App\Models\{Host, Report, User};
use Illuminate\Support\Facades\Mail;

/*
 * Regression tests for the 2026-04-23 incident:
 * postfix stopped for 26h after an unattended-upgrade, and sync-mode queue
 * meant every notification error bubbled up into the HTTP request without
 * any retry. 17 admin/user notifications were lost silently.
 *
 * These tests lock in the retry contract so the same failure mode cannot
 * swallow notifications again once queue=database + worker is active.
 */

describe('retry configuration contract', function () {
    it('SendReportNotificationJob retries 3 times with exponential backoff', function () {
        $job = new SendReportNotificationJob('01971051-306b-70a0-bab8-bd4ccc801000');

        expect($job->tries)->toBe(3);
        expect($job->backoff)->toBe([60, 300, 600]);
        expect($job->timeout)->toBe(120);
    });

    it('SendSimpleUnblockNotificationJob retries 3 times with exponential backoff', function () {
        $job = new SendSimpleUnblockNotificationJob(
            reportId: null,
            email: 'test@example.com',
            domain: 'example.com',
        );

        expect($job->tries)->toBe(3);
        expect($job->backoff)->toBe([60, 300, 600]);
        expect($job->timeout)->toBe(120);
    });

    it('ProcessFirewallCheckJob retries 2 times with backoff', function () {
        $job = new ProcessFirewallCheckJob('1.2.3.4', 1, 1);

        expect($job->tries)->toBe(2);
        expect($job->backoff)->toBe([60, 300]);
        expect($job->timeout)->toBe(300);
    });

    it('ProcessSimpleUnblockJob retries 2 times with backoff', function () {
        $job = new ProcessSimpleUnblockJob('1.2.3.4', 'example.com', 'test@example.com', 1);

        expect($job->tries)->toBe(2);
        expect($job->backoff)->toBe([60, 300]);
        expect($job->timeout)->toBe(300);
    });

    it('ProcessHqWhitelistJob retries 2 times with backoff', function () {
        $job = new ProcessHqWhitelistJob('1.2.3.4', 1);

        expect($job->tries)->toBe(2);
        expect($job->backoff)->toBe([60, 300]);
        expect($job->timeout)->toBe(300);
    });

    it('RemoveExpiredBfmWhitelistIps does NOT retry (periodic cleanup)', function () {
        $job = new RemoveExpiredBfmWhitelistIps;

        // Intentional: the next scheduled run picks up remaining work.
        // Retrying within the same run risks partial double-processing.
        expect($job->tries)->toBe(1);
        expect($job->timeout)->toBe(600);
    });
});

describe('uniqueId contract (prevent duplicate coalesced dispatches)', function () {
    it('SendReportNotificationJob uniqueId is deterministic per (report, copy_user)', function () {
        $a = new SendReportNotificationJob('uuid-1', 42);
        $b = new SendReportNotificationJob('uuid-1', 42);
        $c = new SendReportNotificationJob('uuid-1', 99);

        expect($a->uniqueId())->toBe($b->uniqueId())
            ->and($a->uniqueId())->not->toBe($c->uniqueId());
    });

    it('SendSimpleUnblockNotificationJob uniqueId distinguishes admin-only from user notifications', function () {
        $userNotif = new SendSimpleUnblockNotificationJob(
            reportId: 'uuid-1',
            email: 'test@example.com',
            domain: 'example.com',
            adminOnly: false,
        );
        $adminNotif = new SendSimpleUnblockNotificationJob(
            reportId: 'uuid-1',
            email: 'test@example.com',
            domain: 'example.com',
            adminOnly: true,
        );

        expect($userNotif->uniqueId())->not->toBe($adminNotif->uniqueId());
    });

    it('ProcessFirewallCheckJob uniqueId coalesces same (user, host, ip)', function () {
        $a = new ProcessFirewallCheckJob('1.2.3.4', 1, 2);
        $b = new ProcessFirewallCheckJob('1.2.3.4', 1, 2);
        $c = new ProcessFirewallCheckJob('1.2.3.5', 1, 2);

        expect($a->uniqueId())->toBe($b->uniqueId())
            ->and($a->uniqueId())->not->toBe($c->uniqueId());
    });
});

describe('failure mode: notification job rethrows to trigger retry', function () {
    beforeEach(function () {
        // We deliberately do NOT Mail::fake() here — we want the job's
        // real mail-sending code path to run until the mailer throws.
    });

    it('SendReportNotificationJob rethrows when Mail transport fails', function () {
        // Simulate the exact error from 2026-04-23 incident.
        Mail::shouldReceive('to')
            ->andThrow(new Exception(
                'Connection could not be established with host "argos01.tabratino.com:25": '
                .'stream_socket_client(): Unable to connect (Connection refused)'
            ));

        $user = User::factory()->create();
        $host = Host::factory()->create();
        $report = Report::create([
            'user_id' => $user->id,
            'host_id' => $host->id,
            'ip' => '192.168.1.100',
            'logs' => ['test log entry'],
            'analysis' => ['was_blocked' => true],
        ]);

        $job = new SendReportNotificationJob($report->id);

        // Iron rule: the job MUST rethrow so Laravel's queue worker picks up
        // the retry. A silent failure here would re-introduce the 2026-04-23
        // class of bug (notifications silently lost).
        expect(fn () => $job->handle())
            ->toThrow(Exception::class, 'Connection refused');
    });

    it('SendSimpleUnblockNotificationJob rethrows when Mail transport fails', function () {
        Mail::shouldReceive('to')
            ->andThrow(new Exception('Connection refused'));

        $host = Host::factory()->create();
        $report = Report::create([
            'user_id' => User::factory()->create()->id,
            'host_id' => $host->id,
            'ip' => '192.168.1.100',
            'logs' => [],
            'analysis' => ['was_blocked' => false],
        ]);

        $job = new SendSimpleUnblockNotificationJob(
            reportId: (string) $report->id,
            email: 'user@example.com',
            domain: 'example.com',
        );

        expect(fn () => $job->handle())
            ->toThrow(Exception::class, 'Connection refused');
    });
});
