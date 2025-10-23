<?php

declare(strict_types=1);

use App\Events\SimpleUnblock\SimpleUnblockRequestProcessed;
use App\Listeners\SimpleUnblock\TrackIpReputationListener;
use App\Models\IpReputation;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\{assertDatabaseHas};

beforeEach(function () {
    // Run migrations for reputation tables
    $this->artisan('migrate');
});

test('listener creates new IP reputation record on first request', function () {
    $event = new SimpleUnblockRequestProcessed(
        ip: '192.168.1.100',
        domain: 'example.com',
        email: 'test@example.com',
        success: true
    );

    $listener = new TrackIpReputationListener;
    $listener->handle($event);

    assertDatabaseHas('ip_reputation', [
        'ip' => '192.168.1.100',
        'subnet' => '192.168.1.0/24',
        'reputation_score' => 100,
        'total_requests' => 1,
        'failed_requests' => 0,
    ]);
});

test('listener updates existing IP reputation record', function () {
    // Create initial record
    IpReputation::create([
        'ip' => '192.168.1.100',
        'subnet' => '192.168.1.0/24',
        'reputation_score' => 100,
        'total_requests' => 5,
        'failed_requests' => 0,
        'last_seen_at' => now()->subHour(),
    ]);

    $event = new SimpleUnblockRequestProcessed(
        ip: '192.168.1.100',
        domain: 'example.com',
        email: 'test@example.com',
        success: true
    );

    $listener = new TrackIpReputationListener;
    $listener->handle($event);

    assertDatabaseHas('ip_reputation', [
        'ip' => '192.168.1.100',
        'total_requests' => 6,
        'failed_requests' => 0,
    ]);
});

test('listener tracks failed requests', function () {
    $event = new SimpleUnblockRequestProcessed(
        ip: '192.168.1.100',
        domain: 'example.com',
        email: 'test@example.com',
        success: false
    );

    $listener = new TrackIpReputationListener;
    $listener->handle($event);

    assertDatabaseHas('ip_reputation', [
        'ip' => '192.168.1.100',
        'total_requests' => 1,
        'failed_requests' => 1,
    ]);
});

test('listener calculates reputation score correctly', function () {
    // Create IP with some failed requests
    IpReputation::create([
        'ip' => '192.168.1.100',
        'subnet' => '192.168.1.0/24',
        'reputation_score' => 100,
        'total_requests' => 10,
        'failed_requests' => 3,
        'last_seen_at' => now()->subHour(),
    ]);

    $event = new SimpleUnblockRequestProcessed(
        ip: '192.168.1.100',
        domain: 'example.com',
        email: 'test@example.com',
        success: true
    );

    $listener = new TrackIpReputationListener;
    $listener->handle($event);

    $reputation = IpReputation::where('ip', '192.168.1.100')->first();

    // After 11 total requests with 3 failed: (11-3)/11 * 100 = 72.72 = 72
    expect($reputation->reputation_score)->toBe(72);
});

test('listener handles IPv6 addresses correctly', function () {
    $event = new SimpleUnblockRequestProcessed(
        ip: '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
        domain: 'example.com',
        email: 'test@example.com',
        success: true
    );

    $listener = new TrackIpReputationListener;
    $listener->handle($event);

    assertDatabaseHas('ip_reputation', [
        'ip' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
        'total_requests' => 1,
    ]);

    $reputation = IpReputation::where('ip', '2001:0db8:85a3:0000:0000:8a2e:0370:7334')->first();
    expect($reputation->subnet)->toContain('2001:0db8:85a3');
});

test('listener updates last_seen_at timestamp', function () {
    $oldTimestamp = now()->subHours(2);

    IpReputation::create([
        'ip' => '192.168.1.100',
        'subnet' => '192.168.1.0/24',
        'reputation_score' => 100,
        'total_requests' => 1,
        'failed_requests' => 0,
        'last_seen_at' => $oldTimestamp,
    ]);

    $event = new SimpleUnblockRequestProcessed(
        ip: '192.168.1.100',
        domain: 'example.com',
        email: 'test@example.com',
        success: true
    );

    $listener = new TrackIpReputationListener;
    $listener->handle($event);

    $reputation = IpReputation::where('ip', '192.168.1.100')->first();

    expect($reputation->last_seen_at)
        ->toBeGreaterThan($oldTimestamp);
});

test('listener is queued for async processing', function () {
    Event::fake();

    Event::dispatch(new SimpleUnblockRequestProcessed(
        ip: '192.168.1.100',
        domain: 'example.com',
        email: 'test@example.com',
        success: true
    ));

    Event::assertDispatched(SimpleUnblockRequestProcessed::class);

    // Verify listener implements ShouldQueue
    $listener = new TrackIpReputationListener;
    expect($listener)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});
