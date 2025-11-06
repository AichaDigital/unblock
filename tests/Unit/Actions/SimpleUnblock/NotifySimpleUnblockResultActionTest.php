<?php

declare(strict_types=1);

use App\Actions\SimpleUnblock\{NotifySimpleUnblockResultAction, UnblockDecision};
use App\Jobs\SendSimpleUnblockNotificationJob;
use App\Models\{Host, Report};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Log, Queue};

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Log::spy();
});

// ============================================================================
// SCENARIO 1: Main handle() Method - Normal Flow
// ============================================================================

test('action dispatches notification job with all parameters when IP was blocked', function () {
    $host = Host::factory()->create(['fqdn' => 'host1.example.com']);
    $report = Report::factory()->create(['host_id' => $host->id]);
    $decision = UnblockDecision::unblock('IP was blocked in CSF');

    $analysisData = [
        'csf_blocked' => true,
        'firewall_logs' => ['log1', 'log2'],
    ];

    NotifySimpleUnblockResultAction::run(
        decision: $decision,
        email: 'user@example.com',
        domain: 'example.com',
        report: $report,
        host: $host,
        analysisData: $analysisData
    );

    Queue::assertPushed(SendSimpleUnblockNotificationJob::class, function ($job) use ($report, $host, $decision, $analysisData) {
        return $job->reportId === (string) $report->id
            && $job->email === 'user@example.com'
            && $job->domain === 'example.com'
            && $job->reason === $decision->reason
            && $job->hostFqdn === $host->fqdn
            && $job->analysisData === $analysisData
            && $job->adminOnly === false; // Not admin-only
    });
});

test('action dispatches notification job when IP was NOT blocked', function () {
    $host = Host::factory()->create();
    $report = Report::factory()->create(['host_id' => $host->id]);
    $decision = UnblockDecision::noMatch('IP not found in firewall logs');

    NotifySimpleUnblockResultAction::run(
        decision: $decision,
        email: 'user@example.com',
        domain: 'example.com',
        report: $report,
        host: $host
    );

    Queue::assertPushed(SendSimpleUnblockNotificationJob::class, function ($job) {
        return $job->reason === 'IP not found in firewall logs'
            && $job->adminOnly === false;
    });
});

test('action logs info when dispatching notification', function () {
    $host = Host::factory()->create(['fqdn' => 'host1.example.com']);
    $report = Report::factory()->create(['host_id' => $host->id]);
    $decision = UnblockDecision::unblock('IP blocked and unblocked');

    NotifySimpleUnblockResultAction::run(
        decision: $decision,
        email: 'user@example.com',
        domain: 'testdomain.com',
        report: $report,
        host: $host
    );

    Log::shouldHaveReceived('info')
        ->once()
        ->with('Dispatching notification for simple unblock result.', [
            'decision' => 'IP blocked and unblocked',
            'should_unblock' => true,
            'domain' => 'testdomain.com',
            'report_id' => $report->id,
        ]);
});

// ============================================================================
// SCENARIO 2: Null Parameters
// ============================================================================

test('action handles null report gracefully', function () {
    $host = Host::factory()->create();
    $decision = UnblockDecision::abort('Investigation aborted');

    NotifySimpleUnblockResultAction::run(
        decision: $decision,
        email: 'user@example.com',
        domain: 'example.com',
        report: null, // ← Null report
        host: $host
    );

    Queue::assertPushed(SendSimpleUnblockNotificationJob::class, function ($job) {
        // (string) null = "" (empty string), not null
        return $job->reportId === ''
            && $job->email === 'user@example.com';
    });
});

test('action handles null analysisData gracefully', function () {
    $host = Host::factory()->create();
    $report = Report::factory()->create(['host_id' => $host->id]);
    $decision = UnblockDecision::gatherData('Gathering more data');

    NotifySimpleUnblockResultAction::run(
        decision: $decision,
        email: 'user@example.com',
        domain: 'example.com',
        report: $report,
        host: $host,
        analysisData: null // ← Null analysis data
    );

    Queue::assertPushed(SendSimpleUnblockNotificationJob::class, function ($job) {
        return $job->analysisData === null;
    });
});

test('action logs with null report_id when report is null', function () {
    $host = Host::factory()->create();
    $decision = UnblockDecision::noMatch('No match found');

    NotifySimpleUnblockResultAction::run(
        decision: $decision,
        email: 'user@example.com',
        domain: 'example.com',
        report: null,
        host: $host
    );

    Log::shouldHaveReceived('info')
        ->with('Dispatching notification for simple unblock result.', Mockery::on(function ($context) {
            return $context['report_id'] === null;
        }));
});

// ============================================================================
// SCENARIO 3: Different UnblockDecision Types
// ============================================================================

test('action dispatches notification for unblock decision', function () {
    $host = Host::factory()->create();
    $report = Report::factory()->create(['host_id' => $host->id]);
    $decision = UnblockDecision::unblock('IP was blocked, now unblocked');

    NotifySimpleUnblockResultAction::run(
        decision: $decision,
        email: 'user@example.com',
        domain: 'example.com',
        report: $report,
        host: $host
    );

    Queue::assertPushed(SendSimpleUnblockNotificationJob::class, function ($job) use ($decision) {
        return $job->reason === $decision->reason
            && strpos($decision->reason, 'unblocked') !== false;
    });
});

test('action dispatches notification for gatherData decision', function () {
    $host = Host::factory()->create();
    $report = Report::factory()->create(['host_id' => $host->id]);
    $decision = UnblockDecision::gatherData('Logs found but no block detected');

    NotifySimpleUnblockResultAction::run(
        decision: $decision,
        email: 'user@example.com',
        domain: 'example.com',
        report: $report,
        host: $host
    );

    Queue::assertPushed(SendSimpleUnblockNotificationJob::class, function ($job) use ($decision) {
        return $job->reason === $decision->reason;
    });
});

test('action dispatches notification for noMatch decision', function () {
    $host = Host::factory()->create();
    $report = Report::factory()->create(['host_id' => $host->id]);
    $decision = UnblockDecision::noMatch('No evidence found');

    NotifySimpleUnblockResultAction::run(
        decision: $decision,
        email: 'user@example.com',
        domain: 'example.com',
        report: $report,
        host: $host
    );

    Queue::assertPushed(SendSimpleUnblockNotificationJob::class, function ($job) {
        return $job->reason === 'No evidence found';
    });
});

test('action dispatches notification for abort decision', function () {
    $host = Host::factory()->create();
    $decision = UnblockDecision::abort('Connection failed');

    NotifySimpleUnblockResultAction::run(
        decision: $decision,
        email: 'user@example.com',
        domain: 'example.com',
        report: null,
        host: $host
    );

    Queue::assertPushed(SendSimpleUnblockNotificationJob::class, function ($job) {
        return $job->reason === 'Connection failed'
            && $job->reportId === ''; // (string) null = ""
    });
});

// ============================================================================
// SCENARIO 4: handleSuspiciousAttempt() Method
// ============================================================================

test('handleSuspiciousAttempt dispatches admin-only notification', function () {
    $host = Host::factory()->create(['fqdn' => 'suspicious-host.example.com']);

    (new NotifySimpleUnblockResultAction)->handleSuspiciousAttempt(
        ip: '192.168.1.100',
        domain: 'fake-domain.com',
        email: 'attacker@example.com',
        host: $host,
        reason: 'Domain not found in database'
    );

    Queue::assertPushed(SendSimpleUnblockNotificationJob::class, function ($job) use ($host) {
        return $job->reportId === null
            && $job->email === 'attacker@example.com'
            && $job->domain === 'fake-domain.com'
            && $job->adminOnly === true // ← Admin-only flag
            && $job->reason === 'Domain not found in database'
            && $job->hostFqdn === $host->fqdn
            && isset($job->analysisData['ip'])
            && $job->analysisData['ip'] === '192.168.1.100'
            && $job->analysisData['warning'] === 'Possible abuse attempt - domain validation failed';
    });
});

test('handleSuspiciousAttempt logs warning with details', function () {
    $host = Host::factory()->create(['fqdn' => 'test-host.example.com']);

    (new NotifySimpleUnblockResultAction)->handleSuspiciousAttempt(
        ip: '10.0.0.1',
        domain: 'suspicious-domain.com',
        email: 'suspicious@example.com',
        host: $host,
        reason: 'Account suspended'
    );

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Suspicious simple unblock attempt detected', [
            'ip' => '10.0.0.1',
            'domain' => 'suspicious-domain.com',
            'email' => 'suspicious@example.com',
            'host_fqdn' => 'test-host.example.com',
            'reason' => 'Account suspended',
        ]);
});

test('handleSuspiciousAttempt includes timestamp in analysis data', function () {
    $host = Host::factory()->create();

    (new NotifySimpleUnblockResultAction)->handleSuspiciousAttempt(
        ip: '192.168.1.1',
        domain: 'bad-domain.com',
        email: 'bad@example.com',
        host: $host,
        reason: 'Validation failed'
    );

    Queue::assertPushed(SendSimpleUnblockNotificationJob::class, function ($job) {
        return isset($job->analysisData['timestamp'])
            && is_string($job->analysisData['timestamp'])
            && strlen($job->analysisData['timestamp']) > 10; // ISO timestamp
    });
});

test('handleSuspiciousAttempt includes validation reason in analysis data', function () {
    $host = Host::factory()->create();

    (new NotifySimpleUnblockResultAction)->handleSuspiciousAttempt(
        ip: '203.0.113.1',
        domain: 'test.com',
        email: 'test@example.com',
        host: $host,
        reason: 'Domain belongs to different account'
    );

    Queue::assertPushed(SendSimpleUnblockNotificationJob::class, function ($job) {
        return $job->analysisData['validation_reason'] === 'Domain belongs to different account';
    });
});

// ============================================================================
// SCENARIO 5: Complex Analysis Data
// ============================================================================

test('action preserves complex nested analysis data', function () {
    $host = Host::factory()->create();
    $report = Report::factory()->create(['host_id' => $host->id]);
    $decision = UnblockDecision::unblock('Complex analysis completed');

    $complexAnalysisData = [
        'firewall' => [
            'csf' => [
                'blocked' => true,
                'entries' => ['entry1', 'entry2'],
            ],
            'bfm' => [
                'blocked' => false,
            ],
        ],
        'logs' => [
            'apache' => ['log1', 'log2', 'log3'],
            'exim' => ['log4'],
        ],
        'metrics' => [
            'analysis_time_ms' => 1234,
            'ssh_connections' => 2,
        ],
    ];

    NotifySimpleUnblockResultAction::run(
        decision: $decision,
        email: 'user@example.com',
        domain: 'example.com',
        report: $report,
        host: $host,
        analysisData: $complexAnalysisData
    );

    Queue::assertPushed(SendSimpleUnblockNotificationJob::class, function ($job) use ($complexAnalysisData) {
        return $job->analysisData === $complexAnalysisData
            && $job->analysisData['firewall']['csf']['blocked'] === true
            && $job->analysisData['metrics']['analysis_time_ms'] === 1234;
    });
});

// ============================================================================
// SCENARIO 6: Multiple Hosts and Domains
// ============================================================================

test('action dispatches separate notifications for different hosts', function () {
    $host1 = Host::factory()->create(['fqdn' => 'host1.example.com']);
    $host2 = Host::factory()->create(['fqdn' => 'host2.example.com']);

    $report1 = Report::factory()->create(['host_id' => $host1->id]);
    $report2 = Report::factory()->create(['host_id' => $host2->id]);

    $decision = UnblockDecision::unblock('IP unblocked');

    // Dispatch for host1
    NotifySimpleUnblockResultAction::run(
        decision: $decision,
        email: 'user@example.com',
        domain: 'domain1.com',
        report: $report1,
        host: $host1
    );

    // Dispatch for host2
    NotifySimpleUnblockResultAction::run(
        decision: $decision,
        email: 'user@example.com',
        domain: 'domain2.com',
        report: $report2,
        host: $host2
    );

    // Should have 2 jobs dispatched
    Queue::assertPushed(SendSimpleUnblockNotificationJob::class, 2);

    // Verify each job has correct host
    Queue::assertPushed(SendSimpleUnblockNotificationJob::class, function ($job) {
        return $job->hostFqdn === 'host1.example.com'
            && $job->domain === 'domain1.com';
    });

    Queue::assertPushed(SendSimpleUnblockNotificationJob::class, function ($job) {
        return $job->hostFqdn === 'host2.example.com'
            && $job->domain === 'domain2.com';
    });
});
