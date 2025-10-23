<?php

declare(strict_types=1);

use App\Events\SimpleUnblock\{SimpleUnblockOtpFailed, SimpleUnblockOtpSent, SimpleUnblockOtpVerified};
use App\Listeners\SimpleUnblock\TrackEmailReputationListener;
use App\Models\EmailReputation;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    // Run migrations for reputation tables
    $this->artisan('migrate');
});

test('listener creates new email reputation record on OTP sent', function () {
    $event = new SimpleUnblockOtpSent(
        email: 'test@example.com',
        ip: '192.168.1.100'
    );

    $listener = new TrackEmailReputationListener;
    $listener->handle($event);

    $emailHash = hash('sha256', 'test@example.com');

    assertDatabaseHas('email_reputation', [
        'email_hash' => $emailHash,
        'email_domain' => 'example.com',
        'reputation_score' => 100,
        'total_requests' => 1,
        'failed_requests' => 0,
        'verified_requests' => 0,
    ]);
});

test('listener updates existing email reputation on OTP sent', function () {
    $emailHash = hash('sha256', 'test@example.com');

    EmailReputation::create([
        'email_hash' => $emailHash,
        'email_domain' => 'example.com',
        'reputation_score' => 100,
        'total_requests' => 5,
        'failed_requests' => 0,
        'verified_requests' => 3,
        'last_seen_at' => now()->subHour(),
    ]);

    $event = new SimpleUnblockOtpSent(
        email: 'test@example.com',
        ip: '192.168.1.100'
    );

    $listener = new TrackEmailReputationListener;
    $listener->handle($event);

    assertDatabaseHas('email_reputation', [
        'email_hash' => $emailHash,
        'total_requests' => 6,
        'verified_requests' => 3,
    ]);
});

test('listener tracks verified requests on OTP verified', function () {
    $emailHash = hash('sha256', 'test@example.com');

    EmailReputation::create([
        'email_hash' => $emailHash,
        'email_domain' => 'example.com',
        'reputation_score' => 100,
        'total_requests' => 5,
        'failed_requests' => 0,
        'verified_requests' => 2,
        'last_seen_at' => now()->subHour(),
    ]);

    $event = new SimpleUnblockOtpVerified(
        email: 'test@example.com',
        ip: '192.168.1.100'
    );

    $listener = new TrackEmailReputationListener;
    $listener->handle($event);

    assertDatabaseHas('email_reputation', [
        'email_hash' => $emailHash,
        'total_requests' => 6,
        'verified_requests' => 3,
    ]);
});

test('listener tracks failed requests on OTP failed', function () {
    $emailHash = hash('sha256', 'test@example.com');

    EmailReputation::create([
        'email_hash' => $emailHash,
        'email_domain' => 'example.com',
        'reputation_score' => 100,
        'total_requests' => 5,
        'failed_requests' => 1,
        'verified_requests' => 3,
        'last_seen_at' => now()->subHour(),
    ]);

    $event = new SimpleUnblockOtpFailed(
        email: 'test@example.com',
        ip: '192.168.1.100',
        reason: 'Invalid OTP code'
    );

    $listener = new TrackEmailReputationListener;
    $listener->handle($event);

    assertDatabaseHas('email_reputation', [
        'email_hash' => $emailHash,
        'total_requests' => 6,
        'failed_requests' => 2,
        'verified_requests' => 3,
    ]);
});

test('listener calculates reputation score correctly', function () {
    $emailHash = hash('sha256', 'test@example.com');

    // Create email with some verified and failed requests
    EmailReputation::create([
        'email_hash' => $emailHash,
        'email_domain' => 'example.com',
        'reputation_score' => 100,
        'total_requests' => 10,
        'failed_requests' => 2,
        'verified_requests' => 7,
        'last_seen_at' => now()->subHour(),
    ]);

    $event = new SimpleUnblockOtpVerified(
        email: 'test@example.com',
        ip: '192.168.1.100'
    );

    $listener = new TrackEmailReputationListener;
    $listener->handle($event);

    $reputation = EmailReputation::where('email_hash', $emailHash)->first();

    // After 11 total, 2 failed, 8 verified
    // Success rate: (11-2)/11 * 100 = 81.81 = 81
    // Verified ratio: 8/11 = 0.727 * 20 = 14 bonus
    // Score: min(100, 81 + 14) = 95
    expect($reputation->reputation_score)->toBeGreaterThanOrEqual(90);
});

test('listener extracts email domain correctly', function () {
    $event = new SimpleUnblockOtpSent(
        email: 'user@subdomain.example.com',
        ip: '192.168.1.100'
    );

    $listener = new TrackEmailReputationListener;
    $listener->handle($event);

    $emailHash = hash('sha256', 'user@subdomain.example.com');

    assertDatabaseHas('email_reputation', [
        'email_hash' => $emailHash,
        'email_domain' => 'subdomain.example.com',
    ]);
});

test('listener stores SHA-256 hash not plaintext email (GDPR compliant)', function () {
    $email = 'test@example.com';
    $expectedHash = hash('sha256', $email);

    $event = new SimpleUnblockOtpSent(
        email: $email,
        ip: '192.168.1.100'
    );

    $listener = new TrackEmailReputationListener;
    $listener->handle($event);

    assertDatabaseHas('email_reputation', [
        'email_hash' => $expectedHash,
    ]);

    // Verify plaintext email is NOT stored
    $allRecords = EmailReputation::all();
    foreach ($allRecords as $record) {
        expect($record->email_hash)->not()->toBe($email);
        expect($record->email_hash)->toHaveLength(64); // SHA-256 is 64 chars
    }
});

test('listener updates last_seen_at timestamp', function () {
    $emailHash = hash('sha256', 'test@example.com');
    $oldTimestamp = now()->subHours(2);

    EmailReputation::create([
        'email_hash' => $emailHash,
        'email_domain' => 'example.com',
        'reputation_score' => 100,
        'total_requests' => 1,
        'failed_requests' => 0,
        'verified_requests' => 0,
        'last_seen_at' => $oldTimestamp,
    ]);

    $event = new SimpleUnblockOtpSent(
        email: 'test@example.com',
        ip: '192.168.1.100'
    );

    $listener = new TrackEmailReputationListener;
    $listener->handle($event);

    $reputation = EmailReputation::where('email_hash', $emailHash)->first();

    expect($reputation->last_seen_at)
        ->toBeGreaterThan($oldTimestamp);
});

test('listener is queued for async processing', function () {
    // Verify listener implements ShouldQueue
    $listener = new TrackEmailReputationListener;
    expect($listener)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});
