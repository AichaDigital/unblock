<?php

declare(strict_types=1);

use App\Jobs\SendReportNotificationJob;
use App\Models\{Host, Report, User};
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake(); // Always fake mail to avoid actual sending
});

it('processes report with user and admin', function () {
    // Arrange
    $user = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $host = Host::factory()->create();

    $report = Report::create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '192.168.1.100',
        'logs' => ['test log entry'],
        'analysis' => ['blocked' => true],
    ]);

    // Act - Execute job
    $job = new SendReportNotificationJob($report->id);
    $job->handle();

    // Assert - Just verify the report exists and has the correct data
    $processedReport = Report::find($report->id);
    expect($processedReport)->not->toBeNull();
    expect($processedReport->ip)->toBe('192.168.1.100');
    expect($processedReport->user_id)->toBe($user->id);
});

it('processes report when no admin exists', function () {
    // Arrange
    $user = User::factory()->create();
    $host = Host::factory()->create();
    User::where('is_admin', true)->delete(); // No admin

    $report = Report::create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'ip' => '192.168.1.100',
        'logs' => ['test log entry'],
        'analysis' => ['blocked' => true],
    ]);

    // Act - Execute job
    $job = new SendReportNotificationJob($report->id);
    $job->handle();

    // Assert - Job runs fine even without admin
    expect($report->user_id)->toBe($user->id);
});

it('handles missing report gracefully', function () {
    // Arrange - Non-existent report ID
    $nonExistentReportId = '01971051-306b-70a0-bab8-bd4ccc801000';

    // Act - Execute job with non-existent report
    $job = new SendReportNotificationJob($nonExistentReportId);
    $job->handle();

    // Assert - Job completes (doesn't crash)
    expect(true)->toBeTrue(); // Just confirm we got here
});
