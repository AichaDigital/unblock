<?php

use App\Actions\SimpleUnblock\AnalyzeFirewallForIpAction;
use App\Actions\UnblockIpActionNormalMode;
use App\Jobs\{ProcessFirewallCheckJob, SendReportNotificationJob, SendSimpleUnblockNotificationJob};
use App\Models\{Host, User};
use App\Services\Firewall\FirewallAnalysisResult;
use Illuminate\Support\Facades\Queue;
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
        resolve(\App\Actions\SimpleUnblock\ValidateIpFormatAction::class),
        resolve(\App\Actions\Firewall\ValidateUserAccessToHostAction::class),
        resolve(AnalyzeFirewallForIpAction::class),
        resolve(UnblockIpActionNormalMode::class),
        resolve(\App\Services\ReportGenerator::class),
        resolve(\App\Services\AuditService::class)
    );

    // 3. Assert
    // Assert that the correct notification job for NORMAL mode was pushed
    Queue::assertPushed(SendReportNotificationJob::class);

    // Assert that the notification job for SIMPLE mode was NOT pushed
    Queue::assertNotPushed(SendSimpleUnblockNotificationJob::class);
});
