<?php

declare(strict_types=1);

use App\Actions\CheckRecentBlockHistoryAction;
use App\Models\{Host, Report, User};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->host = Host::factory()->create();
    $this->action = new CheckRecentBlockHistoryAction;
});

test('returns zero count when no reports exist for IP', function () {
    $result = $this->action->handle('192.0.2.50', $this->host->id);

    expect($result)
        ->toBeArray()
        ->toHaveKeys(['count', 'last_blocked_at', 'dates'])
        ->and($result['count'])->toBe(0)
        ->and($result['last_blocked_at'])->toBeNull()
        ->and($result['dates'])->toBeEmpty();
});

test('returns zero count when reports exist but IP was not blocked', function () {
    Report::factory()->count(3)->create([
        'ip' => '192.0.2.50',
        'host_id' => $this->host->id,
        'user_id' => $this->user->id,
        'analysis' => ['was_blocked' => false, 'details' => 'Not blocked'],
    ]);

    $result = $this->action->handle('192.0.2.50', $this->host->id);

    expect($result['count'])->toBe(0)
        ->and($result['dates'])->toBeEmpty();
});

test('counts only reports where IP was blocked', function () {
    // 2 blocked reports
    Report::factory()->count(2)->create([
        'ip' => '192.0.2.50',
        'host_id' => $this->host->id,
        'user_id' => $this->user->id,
        'analysis' => ['was_blocked' => true, 'details' => 'Blocked in CSF'],
    ]);

    // 1 not-blocked report (should be excluded)
    Report::factory()->create([
        'ip' => '192.0.2.50',
        'host_id' => $this->host->id,
        'user_id' => $this->user->id,
        'analysis' => ['was_blocked' => false, 'details' => 'Not blocked'],
    ]);

    $result = $this->action->handle('192.0.2.50', $this->host->id);

    expect($result['count'])->toBe(2)
        ->and($result['dates'])->toHaveCount(2)
        ->and($result['last_blocked_at'])->not->toBeNull();
});

test('scopes results to specific host', function () {
    $otherHost = Host::factory()->create();

    // Report on target host
    Report::factory()->create([
        'ip' => '192.0.2.50',
        'host_id' => $this->host->id,
        'user_id' => $this->user->id,
        'analysis' => ['was_blocked' => true],
    ]);

    // Report on different host (should be excluded)
    Report::factory()->create([
        'ip' => '192.0.2.50',
        'host_id' => $otherHost->id,
        'user_id' => $this->user->id,
        'analysis' => ['was_blocked' => true],
    ]);

    $result = $this->action->handle('192.0.2.50', $this->host->id);

    expect($result['count'])->toBe(1);
});

test('excludes reports older than specified days', function () {
    // Recent report (within 7 days)
    Report::factory()->create([
        'ip' => '192.0.2.50',
        'host_id' => $this->host->id,
        'user_id' => $this->user->id,
        'analysis' => ['was_blocked' => true],
        'created_at' => now()->subDays(3),
    ]);

    // Old report (beyond 7 days)
    Report::factory()->create([
        'ip' => '192.0.2.50',
        'host_id' => $this->host->id,
        'user_id' => $this->user->id,
        'analysis' => ['was_blocked' => true],
        'created_at' => now()->subDays(10),
    ]);

    $result = $this->action->handle('192.0.2.50', $this->host->id);

    expect($result['count'])->toBe(1)
        ->and($result['dates'])->toHaveCount(1);
});

test('respects custom days parameter', function () {
    Report::factory()->create([
        'ip' => '192.0.2.50',
        'host_id' => $this->host->id,
        'user_id' => $this->user->id,
        'analysis' => ['was_blocked' => true],
        'created_at' => now()->subDays(3),
    ]);

    // Within 7 days but outside 2 days
    $resultShort = $this->action->handle('192.0.2.50', $this->host->id, 2);
    $resultLong = $this->action->handle('192.0.2.50', $this->host->id, 7);

    expect($resultShort['count'])->toBe(0)
        ->and($resultLong['count'])->toBe(1);
});

test('limits results to 10 most recent', function () {
    Report::factory()->count(15)->create([
        'ip' => '192.0.2.50',
        'host_id' => $this->host->id,
        'user_id' => $this->user->id,
        'analysis' => ['was_blocked' => true],
    ]);

    $result = $this->action->handle('192.0.2.50', $this->host->id);

    expect($result['count'])->toBe(10)
        ->and($result['dates'])->toHaveCount(10);
});

test('returns dates in d/m/Y H:i format', function () {
    Report::factory()->create([
        'ip' => '192.0.2.50',
        'host_id' => $this->host->id,
        'user_id' => $this->user->id,
        'analysis' => ['was_blocked' => true],
        'created_at' => now()->parse('2026-02-15 14:30:00'),
    ]);

    $result = $this->action->handle('192.0.2.50', $this->host->id);

    expect($result['dates'][0])->toMatch('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/');
});

test('last_blocked_at returns ISO string of most recent block', function () {
    Report::factory()->create([
        'ip' => '192.0.2.50',
        'host_id' => $this->host->id,
        'user_id' => $this->user->id,
        'analysis' => ['was_blocked' => true],
        'created_at' => now()->subDays(5),
    ]);

    Report::factory()->create([
        'ip' => '192.0.2.50',
        'host_id' => $this->host->id,
        'user_id' => $this->user->id,
        'analysis' => ['was_blocked' => true],
        'created_at' => now()->subDay(),
    ]);

    $result = $this->action->handle('192.0.2.50', $this->host->id);

    expect($result['last_blocked_at'])->toContain(now()->subDay()->format('Y-m-d'));
});
