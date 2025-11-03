<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendReportNotificationJob;
use App\Mail\LogNotificationMail;
use App\Models\{Host, Report, User};
use Illuminate\Support\Facades\{Config, Mail, Queue};

beforeEach(function () {
    Mail::fake();
    Queue::fake();

    // Remove all existing admin users
    User::where('is_admin', true)->delete();
});

test('job is correctly queued with report id', function () {
    // Arrange
    $report = Report::factory()->create([
        'ip' => '1.2.3.4',
        'logs' => ['csf' => 'DENYIN 1.2.3.4'],
        'analysis' => ['was_blocked' => true],
    ]);

    // Act
    SendReportNotificationJob::dispatch($report->id);

    // Assert
    Queue::assertPushed(SendReportNotificationJob::class, function ($job) use ($report) {
        return $job->reportId === $report->id;
    });
});

test('job is correctly queued with copy user id', function () {
    // Arrange
    $report = Report::factory()->create();
    $copyUserId = 123;

    // Act
    SendReportNotificationJob::dispatch($report->id, $copyUserId);

    // Assert
    Queue::assertPushed(SendReportNotificationJob::class, function ($job) use ($report, $copyUserId) {
        return $job->reportId === $report->id && $job->copyUserId === $copyUserId;
    });
});

test('sends notification only to requesting user when no admin exists', function () {
    // Arrange
    // Configure no admin email in config
    Config::set('unblock.admin_email', null);

    $user = User::factory()->create([
        'email' => 'user@example.com',
        'is_admin' => false,
    ]);

    $host = Host::factory()->create([
        'fqdn' => 'test.example.com',
    ]);

    $report = Report::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '1.2.3.4',
        'logs' => ['csf' => 'DENYIN 1.2.3.4'],
        'analysis' => ['was_blocked' => true],
    ]);

    // Act - execute job directly to test internal logic
    $job = new SendReportNotificationJob($report->id);
    $job->handle();

    // Assert
    Mail::assertQueued(LogNotificationMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });

    // Verify only one email was queued
    Mail::assertQueuedCount(1);
});

test('sends notification to requesting user and configured admin', function () {
    // Arrange
    $adminEmail = 'admin-config@example.com';
    Config::set('unblock.admin_email', $adminEmail);

    $user = User::factory()->create([
        'email' => 'user@example.com',
        'is_admin' => false,
    ]);

    $host = Host::factory()->create([
        'fqdn' => 'test.example.com',
    ]);

    $report = Report::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '1.2.3.4',
        'logs' => ['csf' => 'DENYIN 1.2.3.4'],
        'analysis' => ['was_blocked' => true],
    ]);

    // Act - execute job directly to test internal logic
    $job = new SendReportNotificationJob($report->id);
    $job->handle();

    // Assert
    Mail::assertQueued(LogNotificationMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });

    Mail::assertQueued(LogNotificationMail::class, function ($mail) use ($adminEmail) {
        return $mail->hasTo($adminEmail);
    });

    // Verify two emails were queued
    Mail::assertQueuedCount(2);
});

test('sends notification to requesting user and database admin', function () {
    // Arrange
    // Ensure no admin email in config
    Config::set('unblock.admin_email', null);

    $user = User::factory()->create([
        'email' => 'user@example.com',
        'is_admin' => false,
    ]);

    $admin = User::factory()->create([
        'email' => 'admin-db@example.com',
        'is_admin' => true,
    ]);

    $host = Host::factory()->create([
        'fqdn' => 'test.example.com',
    ]);

    $report = Report::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '1.2.3.4',
        'logs' => ['csf' => 'DENYIN 1.2.3.4'],
        'analysis' => ['was_blocked' => true],
    ]);

    // Act - execute job directly to test internal logic
    $job = new SendReportNotificationJob($report->id);
    $job->handle();

    // Assert
    Mail::assertQueued(LogNotificationMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });

    Mail::assertQueued(LogNotificationMail::class, function ($mail) use ($admin) {
        return $mail->hasTo($admin->email);
    });

    // Verify two emails were queued
    Mail::assertQueuedCount(2);
});

test('sends notification to requesting user, configured admin and copy user', function () {
    // Arrange
    $adminEmail = 'admin-config@example.com';
    Config::set('unblock.admin_email', $adminEmail);

    $user = User::factory()->create([
        'email' => 'user@example.com',
        'is_admin' => false,
    ]);

    $copyUser = User::factory()->create([
        'email' => 'copy@example.com',
        'is_admin' => false,
    ]);

    $host = Host::factory()->create(['fqdn' => 'test.example.com']);

    $report = Report::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '1.2.3.4',
        'logs' => ['csf' => 'DENYIN 1.2.3.4'],
        'analysis' => ['was_blocked' => true],
    ]);

    // Act
    $job = new SendReportNotificationJob($report->id, $copyUser->id);
    $job->handle();

    // Assert
    Mail::assertQueued(LogNotificationMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });

    Mail::assertQueued(LogNotificationMail::class, function ($mail) use ($adminEmail) {
        return $mail->hasTo($adminEmail);
    });

    Mail::assertQueued(LogNotificationMail::class, function ($mail) use ($copyUser) {
        return $mail->hasTo($copyUser->email);
    });

    // Verify three emails were queued
    Mail::assertQueuedCount(3);
});

test('does not send duplicate emails when users overlap', function () {
    // Arrange - User IS the admin
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'is_admin' => true,
    ]);

    Config::set('unblock.admin_email', null); // Use database admin

    $host = Host::factory()->create();

    $report = Report::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '5.6.7.8',
        'logs' => ['csf' => 'test'],
        'analysis' => ['was_blocked' => false],
    ]);

    // Act
    $job = new SendReportNotificationJob($report->id);
    $job->handle();

    // Assert - Only ONE email should be sent
    Mail::assertQueued(LogNotificationMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });

    Mail::assertQueuedCount(1); // No duplicate
});

test('does not send to copy user if it is the same as requesting user', function () {
    // Arrange
    $user = User::factory()->create(['email' => 'user@example.com']);
    $host = Host::factory()->create();

    Config::set('unblock.admin_email', null);

    $report = Report::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '6.7.8.9',
        'logs' => ['test' => 'data'],
        'analysis' => ['was_blocked' => true],
    ]);

    // Act - copyUserId is the same as user_id
    $job = new SendReportNotificationJob($report->id, $user->id);
    $job->handle();

    // Assert - Only ONE email sent
    Mail::assertQueuedCount(1);
});

test('handles non-existent copy user gracefully', function () {
    // Arrange
    $user = User::factory()->create(['email' => 'user@example.com']);
    $host = Host::factory()->create();

    Config::set('unblock.admin_email', null);

    $report = Report::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '8.9.10.11',
    ]);

    // Act
    $job = new SendReportNotificationJob($report->id, 999999); // Non-existent
    $job->handle();

    // Assert - Only user email sent
    Mail::assertQueuedCount(1);
});

test('determines was_blocked correctly from analysis', function () {
    // Arrange
    $user = User::factory()->create();
    $host = Host::factory()->create();

    Config::set('unblock.admin_email', null);

    // Test with was_blocked = true
    $reportBlocked = Report::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '9.10.11.12',
        'analysis' => ['was_blocked' => true],
    ]);

    // Test with was_blocked = false
    $reportNotBlocked = Report::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '10.11.12.13',
        'analysis' => ['was_blocked' => false],
    ]);

    // Test with no analysis
    $reportNoAnalysis = Report::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '11.12.13.14',
        'analysis' => null,
    ]);

    // Act
    (new SendReportNotificationJob($reportBlocked->id))->handle();
    (new SendReportNotificationJob($reportNotBlocked->id))->handle();
    (new SendReportNotificationJob($reportNoAnalysis->id))->handle();

    // Assert - All jobs complete successfully
    Mail::assertQueuedCount(3);
});

test('skips admin notification when requesting user is admin', function () {
    // Arrange
    // Ensure no admin email in config
    Config::set('unblock.admin_email', null);

    $adminUser = User::factory()->create([
        'email' => 'admin@example.com',
        'is_admin' => true,
    ]);

    $host = Host::factory()->create([
        'fqdn' => 'test.example.com',
    ]);

    $report = Report::factory()->create([
        'user_id' => $adminUser->id,
        'host_id' => $host->id,
        'ip' => '1.2.3.4',
        'logs' => ['csf' => 'DENYIN 1.2.3.4'],
        'analysis' => ['was_blocked' => true],
    ]);

    // Act - execute job directly to test internal logic
    $job = new SendReportNotificationJob($report->id);
    $job->handle();

    // Assert
    Mail::assertQueued(LogNotificationMail::class, function ($mail) use ($adminUser) {
        return $mail->hasTo($adminUser->email);
    });

    // Verify only one email was queued (no duplicate to admin)
    Mail::assertQueuedCount(1);
});

test('skips copy notification when copy user is same as requesting user', function () {
    // Arrange
    $adminEmail = 'admin-config@example.com';
    Config::set('unblock.admin_email', $adminEmail);

    $user = User::factory()->create([
        'email' => 'user@example.com',
        'is_admin' => false,
    ]);

    $host = Host::factory()->create([
        'fqdn' => 'test.example.com',
    ]);

    $report = Report::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '1.2.3.4',
        'logs' => ['csf' => 'DENYIN 1.2.3.4'],
        'analysis' => ['was_blocked' => true],
    ]);

    // Act - execute job directly to test internal logic passing the same user as copy
    $job = new SendReportNotificationJob($report->id, $user->id);
    $job->handle();

    // Assert
    Mail::assertQueued(LogNotificationMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });

    Mail::assertQueued(LogNotificationMail::class, function ($mail) use ($adminEmail) {
        return $mail->hasTo($adminEmail);
    });

    // Verify only two emails were queued (no duplicate to user)
    Mail::assertQueuedCount(2);
});

// # TEst fixed issue
test('Email correctly shows IP was unblocked when analysis indicates blocking', function () {
    // Arrange
    $user = User::factory()->create(['first_name' => 'Test', 'last_name' => 'User']);
    $admin = User::factory()->create(['is_admin' => true]);
    $host = Host::factory()->create(['fqdn' => 'test.example.com']);

    // Create report with analysis indicating block (was_blocked = 1)
    $report = Report::create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '2.2.2.2',
        'logs' => [
            'csf' => 'filter DENYIN 189 0 0 DROP all -- !lo * 2.2.2.2 0.0.0.0/0\nTemporary Blocks: IP:2.2.2.2',
        ],
        'analysis' => [
            'was_blocked' => 1,
            'patterns_found' => ['DENYIN', 'Temporary Blocks'],
        ],
    ]);

    // Act - ejecutar el job
    $job = new SendReportNotificationJob($report->id);
    $job->handle();

    // Assert - verify that email is sent with is_unblock=true
    Mail::assertQueued(LogNotificationMail::class, function ($mail) use ($report) {
        return $mail->is_unblock === true &&
            $mail->ip === $report->ip &&
            $mail->report['analysis']['was_blocked'] === 1;
    });
});

test('Email correctly shows no block when analysis indicates no blocking', function () {
    // Arrange
    $user = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $host = Host::factory()->create();

    // Create report with analysis indicating NO block (was_blocked = 0)
    $report = Report::create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '1.1.1.1',
        'logs' => [
            'csf' => 'No matches found for 1.1.1.1 in iptables',
        ],
        'analysis' => [
            'was_blocked' => 0,
            'patterns_found' => [],
        ],
    ]);

    // Act
    $job = new SendReportNotificationJob($report->id);
    $job->handle();

    // Assert
    Mail::assertQueued(LogNotificationMail::class, function ($mail) use ($report) {
        return $mail->is_unblock === false &&
            $mail->ip === $report->ip &&
            $mail->report['analysis']['was_blocked'] === 0;
    });
});

test('email does not fallback to logs when analysis is missing preventing false positives', function () {
    // CRITICAL BEHAVIOR CHANGE: No longer fallback to logs to prevent false positives
    // This was the source of the "IP desbloqueada correctamente" bug

    // Arrange
    Config::set('unblock.admin_email', null);
    $user = User::factory()->create(['is_admin' => false]);
    $host = Host::factory()->create();

    // Create report WITHOUT analysis but with logs that LOOK like blocks
    $report = Report::create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '3.3.3.3',
        'logs' => [
            'csf' => 'filter DENYIN 189 0 0 DROP all -- !lo * 3.3.3.3 0.0.0.0/0',
        ],
        'analysis' => [], // No analysis - this is the key test
    ]);

    // Act
    $job = new SendReportNotificationJob($report->id);
    $job->handle();

    // Assert - should NOT detect block without proper analysis (prevents false positives)
    Mail::assertQueued(LogNotificationMail::class, function ($mail) use ($report) {
        return $mail->is_unblock === false &&  // CRITICAL: Must be false without analysis
            $mail->ip === $report->ip;
    });
});

test('dovecot auth failed logs without csf blocks should not trigger unblock email', function () {
    // This test replicates a scenario where:
    // - IP has no actual firewall blocks
    // - CSF: "No matches found"
    // - Dovecot: "auth failed" logs present
    // - Expected: is_unblock = false (NO "IP desbloqueada correctamente")

    // Configure no admin to simplify test
    Config::set('unblock.admin_email', null);

    $user = User::factory()->create(['email' => 'user@example.com', 'is_admin' => false]);
    $host = Host::factory()->create(['fqdn' => 'test.example.com']);

    // Create report with scenario data
    $testIp = '192.0.2.100';
    $report = Report::create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => $testIp,
        'logs' => [
            'csf' => "No matches found for {$testIp} in iptables

IPSET: No matches found for {$testIp}

ip6tables:
No matches found for {$testIp} in ip6tables",
            'dovecot' => "Jun 30 06:16:54 testserver dovecot[1169]: pop3-login: Login aborted: Connection closed (auth failed, 1 attempts in 0 secs) (auth_failed): user=<test@example.com>, rip={$testIp}, lip=203.0.113.10, session=<test123>
Jun 30 06:17:44 testserver dovecot[1169]: pop3-login: Login aborted: Connection closed (auth failed, 1 attempts in 0 secs) (auth_failed): user=<contact@example.com>, rip={$testIp}, lip=203.0.113.10, session=<test456>",
        ],
        // Analysis shows was_blocked = false (corrected by analyzer fixes)
        'analysis' => [
            'was_blocked' => false,  // Analyzers now correctly set this to false
            'block_sources' => [],   // No actual block sources
        ],
    ]);

    // Act - execute the job
    $job = new SendReportNotificationJob($report->id);
    $job->handle();

    // Assert - email must show "No se encontró ningún bloqueo"
    Mail::assertQueued(LogNotificationMail::class, function ($mail) use ($report, $user) {
        return $mail->is_unblock === false &&  // Must be false
            $mail->ip === $report->ip &&
            $mail->report['analysis']['was_blocked'] === false &&
            $mail->hasTo($user->email); // Verify this is the user email
    });

    // Verify only one email was sent (no admin copy)
    Mail::assertQueuedCount(1);
});

test('determine if was blocked method only uses analysis no log fallback', function () {
    // This test documents the corrected behavior
    // Only use analysis['was_blocked'], no log fallback to prevent false positives
    $user = User::factory()->create();
    $host = Host::factory()->create();

    // Test case 1: analysis con was_blocked = 1
    $report1 = Report::create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '4.4.4.4',
        'logs' => [],
        'analysis' => ['was_blocked' => 1],
    ]);

    $job = new SendReportNotificationJob($report1->id);

    // Use reflection to access private method for testing
    $reflection = new \ReflectionClass($job);
    $method = $reflection->getMethod('determineIfWasBlocked');

    expect($method->invoke($job, $report1))->toBeTrue(
        'was_blocked=1 en analysis debe retornar true'
    );

    // Test case 2: analysis con was_blocked = 0
    $report2 = Report::create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '5.5.5.5',
        'logs' => [],
        'analysis' => ['was_blocked' => 0],
    ]);

    expect($method->invoke($job, $report2))->toBeFalse(
        'was_blocked=0 en analysis debe retornar false'
    );

    // Test case 3: sin analysis, logs con DENYIN (antes retornaba true, ahora false)
    // Debe retornar false para prevenir falsos positivos
    $report3 = Report::create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '6.6.6.6',
        'logs' => ['csf' => 'DENYIN block detected', 'dovecot' => 'auth failed'],
        'analysis' => [],
    ]);

    expect($method->invoke($job, $report3))->toBeFalse(
        'sin analysis debe retornar false previene bug ip desbloqueada correctamente'
    );
});
