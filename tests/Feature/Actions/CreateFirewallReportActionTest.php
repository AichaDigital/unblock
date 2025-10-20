<?php

use App\Actions\CreateFirewallReportAction;
use App\Models\{Host, Report, User};
use Tests\FirewallTestConstants as TC;

beforeEach(function () {
    $this->action = new CreateFirewallReportAction;
});

test('creates report successfully', function () {
    // Arrange
    $user = User::factory()->create();
    $host = Host::factory()->create();
    $checkResults = [
        'logs' => ['csf' => 'test log'],
        'is_blocked' => true,
    ];
    $analysisResults = [
        'analysis' => ['details' => 'test analysis'],
        'was_blocked' => true,
    ];

    // Act
    $result = $this->action->handle(
        userId: $user->id,
        hostId: $host->id,
        ip: TC::TEST_IP,
        checkResults: $checkResults,
        analysisResults: $analysisResults
    );

    // Assert
    expect($result)
        ->toBeArray()
        ->toHaveKey('report_id')
        ->and($result['success'])->toBeTrue();

    // Verify report was created
    $report = Report::find($result['report_id']);
    expect($report)
        ->not->toBeNull()
        ->and($report->user_id)->toBe($user->id)
        ->and($report->host_id)->toBe($host->id)
        ->and($report->ip)->toBe(TC::TEST_IP)
        ->and($report->logs)->toBe($checkResults['logs'])
        ->and($report->analysis)->toBe($analysisResults['analysis']);
});

test('handles invalid user', function () {
    // Arrange
    $host = Host::factory()->create();

    // Act
    $result = $this->action->handle(
        userId: 999,
        hostId: $host->id,
        ip: TC::TEST_IP,
        checkResults: ['logs' => []],
        analysisResults: ['analysis' => []]
    );

    // Assert
    expect($result)
        ->toBeArray()
        ->toHaveKey('error')
        ->and($result['success'])->toBeFalse();
});

test('handles invalid host', function () {
    // Arrange
    $user = User::factory()->create();

    // Act
    $result = $this->action->handle(
        userId: $user->id,
        hostId: 999,
        ip: TC::TEST_IP,
        checkResults: ['logs' => []],
        analysisResults: ['analysis' => []]
    );

    // Assert
    expect($result)
        ->toBeArray()
        ->toHaveKey('error')
        ->and($result['success'])->toBeFalse();
});

test('handles empty results', function () {
    // Arrange
    $user = User::factory()->create();
    $host = Host::factory()->create();

    // Act
    $result = $this->action->handle(
        userId: $user->id,
        hostId: $host->id,
        ip: TC::TEST_IP,
        checkResults: [],
        analysisResults: []
    );

    // Assert
    expect($result)
        ->toBeArray()
        ->toHaveKey('report_id')
        ->and($result['success'])->toBeTrue();

    // Verify report was created with empty data
    $report = Report::find($result['report_id']);
    expect($report)
        ->not->toBeNull()
        ->and($report->logs)->toBe([])
        ->and($report->analysis)->toBe([]);
});
