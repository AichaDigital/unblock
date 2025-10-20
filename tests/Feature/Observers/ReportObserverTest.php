<?php

declare(strict_types=1);

use App\Models\{Host, Report, User};
use Illuminate\Support\Facades\{Log, Mail, Queue};

beforeEach(function () {
    // Fake mail and queue to avoid actual sending
    Mail::fake();
    Queue::fake();
    Log::spy(); // Spy on Log to verify debug messages
});

it('does not dispatch notification job when report is created (observer disabled)', function () {
    // Arrange - Prepare ALL necessary data for normal operation
    $user = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $host = Host::factory()->create();

    // Verify initial state: no reports exist
    expect(Report::count())->toBe(0);

    // Act - Create report (observer should NOT dispatch job)
    $report = Report::create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '192.168.1.100',
        'logs' => ['test log entry'],
        'analysis' => ['blocked' => true],
    ]);

    // Assert - Verify report was created successfully
    expect(Report::count())->toBe(1);
    expect($report->user_id)->toBe($user->id);
    expect($report->host_id)->toBe($host->id);
    expect($report->ip)->toBe('192.168.1.100');
    expect($report->logs)->toBe(['test log entry']);
    expect($report->analysis)->toBe(['blocked' => true]);

    // Assert - Verify observer did NOT dispatch notification job (intentionally disabled)
    Queue::assertNothingPushed();

    // Assert - Verify debug log was written
    Log::shouldHaveReceived('debug')
        ->with('Report created, notifications handled manually', \Mockery::type('array'))
        ->once();
});

it('logs debug information when report is created', function () {
    // Arrange
    $user = User::factory()->create();
    $host = Host::factory()->create();

    // Act - Create report
    $report = Report::create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '10.0.0.1',
        'logs' => ['firewall analysis complete'],
        'analysis' => ['blocked' => false],
    ]);

    // Assert - Verify debug log contains correct information
    Log::shouldHaveReceived('debug')
        ->with('Report created, notifications handled manually', [
            'report_id' => $report->id,
            'user_id' => $report->user_id,
            'host_id' => $report->host_id,
            'ip' => $report->ip,
        ])
        ->once();

    // Assert - Verify no jobs were dispatched
    Queue::assertNothingPushed();
});

it('verifies manual notification handling is documented', function () {
    // This test verifies the current design decision is documented
    $observerContent = file_get_contents(app_path('Observers/ReportObserver.php'));

    // Verify the observer contains documentation about manual handling
    expect($observerContent)->toContain('DISABLED: Notifications are handled manually')
        ->and($observerContent)->toContain('CheckFirewallAction')
        ->and($observerContent)->toContain('copyUserId parameter');

    // Verify the created method exists but doesn't dispatch jobs
    expect($observerContent)->toContain('public function created(Report $report): void')
        ->and($observerContent)->not->toContain('SendReportNotificationJob::dispatch');
});
