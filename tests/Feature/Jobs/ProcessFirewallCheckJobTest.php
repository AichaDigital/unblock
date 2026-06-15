<?php

use App\Actions\Firewall\ValidateUserAccessToHostAction;
use App\Actions\SimpleUnblock\{AnalyzeFirewallForIpAction, ValidateIpFormatAction};
use App\Actions\UnblockIpActionNormalMode;
use App\Exceptions\{ConnectionFailedException, FirewallException};
use App\Jobs\{ProcessFirewallCheckJob, SendReportNotificationJob, SendSimpleUnblockNotificationJob};
use App\Mail\{AdminConnectionErrorMail, UserSystemErrorMail};
use App\Models\{Host, User};
use App\Services\{AuditService, ReportGenerator};
use App\Services\Firewall\FirewallAnalysisResult;
use Illuminate\Support\Facades\{Mail, Queue};
use Mockery\MockInterface;

test('job orchestrates actions and dispatches correct notification for normal mode', function () {
    // 1. Arrange
    Queue::fake();
    $user = User::factory()->create();
    $host = Host::factory()->create();
    $user->hosts()->attach($host); // Grant user access to the host

    // Mock the analysis result to simulate a blocked IP
    $analysisResult = new FirewallAnalysisResult(true, ['csf' => 'Blocked for testing']);

    // Mock the actions that the job depends on
    $this->mock(AnalyzeFirewallForIpAction::class, function (MockInterface $mock) use ($analysisResult) {
        $mock->shouldReceive('handle')->once()->andReturn($analysisResult);
    });

    $this->mock(UnblockIpActionNormalMode::class, function (MockInterface $mock) {
        $mock->shouldReceive('handle')->once()->andReturn(['success' => true]);
    });

    // 2. Act
    $job = new ProcessFirewallCheckJob(
        ip: '1.2.3.4',
        userId: $user->id,
        hostId: $host->id
    );
    $job->handle(
        resolve(ValidateIpFormatAction::class),
        resolve(ValidateUserAccessToHostAction::class),
        resolve(AnalyzeFirewallForIpAction::class),
        resolve(UnblockIpActionNormalMode::class),
        resolve(ReportGenerator::class),
        resolve(AuditService::class)
    );

    // 3. Assert
    // Assert that the correct notification job for NORMAL mode was pushed
    Queue::assertPushed(SendReportNotificationJob::class);

    // Assert that the notification job for SIMPLE mode was NOT pushed
    Queue::assertNotPushed(SendSimpleUnblockNotificationJob::class);
});

test('failed() notifies the requesting user and the admin when the host is unreachable', function () {
    Mail::fake();
    config(['unblock.admin_email' => 'admin@example.com']);

    $user = User::factory()->create(['email' => 'requester@example.com']);
    $host = Host::factory()->create(['fqdn' => 'srv-unreachable.example.com', 'port_ssh' => 2244]);

    $ip = '203.0.113.45'; // RFC 5737 TEST-NET-3 (documentation range)

    $job = new ProcessFirewallCheckJob(ip: $ip, userId: $user->id, hostId: $host->id);

    // Mirrors production: handle() wraps the SSH connection failure in a FirewallException.
    $exception = new FirewallException(
        "Firewall check failed for IP {$ip} on host ID {$host->id}: SSH connection failed",
        previous: new ConnectionFailedException(
            'SSH connection failed to srv-unreachable.example.com: exceeded the timeout of 30 seconds'
        ),
    );

    $job->failed($exception);

    Mail::assertSent(UserSystemErrorMail::class, fn (UserSystemErrorMail $mail) => $mail->hasTo('requester@example.com')
        && $mail->ip === $ip
        && $mail->host->id === $host->id
        && $mail->user->id === $user->id
    );

    Mail::assertSent(AdminConnectionErrorMail::class, fn (AdminConnectionErrorMail $mail) => $mail->hasTo('admin@example.com')
        && $mail->ip === $ip
        && $mail->host->id === $host->id
    );
});

test('failed() does not send connection alerts for non-connection failures', function () {
    Mail::fake();
    config(['unblock.admin_email' => 'admin@example.com']);

    $user = User::factory()->create();
    $host = Host::factory()->create();

    $job = new ProcessFirewallCheckJob(ip: '203.0.113.45', userId: $user->id, hostId: $host->id);

    // A generic failure with no ConnectionFailedException anywhere in the chain.
    $job->failed(new RuntimeException('some unrelated failure'));

    Mail::assertNotSent(UserSystemErrorMail::class);
    Mail::assertNotSent(AdminConnectionErrorMail::class);
});
