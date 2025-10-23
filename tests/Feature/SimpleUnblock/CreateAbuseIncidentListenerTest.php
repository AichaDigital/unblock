<?php

declare(strict_types=1);

use App\Events\SimpleUnblock\{SimpleUnblockHoneypotTriggered, SimpleUnblockIpMismatch, SimpleUnblockOtpFailed, SimpleUnblockRateLimitExceeded};
use App\Listeners\SimpleUnblock\CreateAbuseIncidentListener;
use App\Models\{AbuseIncident, EmailReputation, IpReputation};
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\{assertDatabaseHas};

beforeEach(function () {
    // Run migrations for reputation tables
    $this->artisan('migrate');
});

test('listener creates abuse incident on rate limit exceeded', function () {
    $event = new SimpleUnblockRateLimitExceeded(
        vector: 'ip',
        identifier: '192.168.1.100',
        attempts: 15,
        maxAttempts: 10
    );

    $listener = new CreateAbuseIncidentListener;
    $listener->handle($event);

    assertDatabaseHas('abuse_incidents', [
        'incident_type' => 'rate_limit_exceeded',
        'ip_address' => '192.168.1.100',
        'severity' => 'low',
    ]);
});

test('listener sets correct severity for rate limit based on vector', function () {
    // Test subnet vector (high severity)
    $listener = new CreateAbuseIncidentListener;

    $event = new SimpleUnblockRateLimitExceeded(
        vector: 'subnet',
        identifier: '192.168.1.0/24',
        attempts: 100,
        maxAttempts: 50
    );
    $listener->handle($event);

    assertDatabaseHas('abuse_incidents', [
        'incident_type' => 'rate_limit_exceeded',
        'severity' => 'high',
    ]);

    // Test email vector (medium severity)
    $event = new SimpleUnblockRateLimitExceeded(
        vector: 'email',
        identifier: hash('sha256', 'test@example.com'),
        attempts: 15,
        maxAttempts: 10
    );
    $listener->handle($event);

    assertDatabaseHas('abuse_incidents', [
        'incident_type' => 'rate_limit_exceeded',
        'severity' => 'medium',
    ]);
});

test('listener creates abuse incident on honeypot triggered', function () {
    $event = new SimpleUnblockHoneypotTriggered(
        ip: '192.168.1.100',
        email: 'bot@spam.com',
        domain: 'spam.com'
    );

    $listener = new CreateAbuseIncidentListener;
    $listener->handle($event);

    $emailHash = hash('sha256', 'bot@spam.com');

    assertDatabaseHas('abuse_incidents', [
        'incident_type' => 'honeypot_triggered',
        'ip_address' => '192.168.1.100',
        'email_hash' => $emailHash,
        'domain' => 'spam.com',
        'severity' => 'medium',
    ]);
});

test('listener creates abuse incident on OTP failed', function () {
    $event = new SimpleUnblockOtpFailed(
        email: 'test@example.com',
        ip: '192.168.1.100',
        reason: 'Invalid OTP code'
    );

    $listener = new CreateAbuseIncidentListener;
    $listener->handle($event);

    $emailHash = hash('sha256', 'test@example.com');

    assertDatabaseHas('abuse_incidents', [
        'incident_type' => 'invalid_otp_attempts',
        'ip_address' => '192.168.1.100',
        'email_hash' => $emailHash,
        'severity' => 'medium',
    ]);
});

test('listener escalates severity on multiple OTP failures', function () {
    $emailHash = hash('sha256', 'test@example.com');
    $listener = new CreateAbuseIncidentListener;

    $timestamp = now()->subMinutes(5);

    // Create 3 recent failures using DB facade (same as listener)
    for ($i = 0; $i < 3; $i++) {
        DB::table('abuse_incidents')->insert([
            'incident_type' => 'invalid_otp_attempts',
            'ip_address' => '192.168.1.100',
            'email_hash' => $emailHash,
            'severity' => 'medium',
            'description' => 'OTP verification failed',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    // Trigger 4th failure (should escalate to high because recentFailures will be >= 3)
    $event = new SimpleUnblockOtpFailed(
        email: 'test@example.com',
        ip: '192.168.1.100',
        reason: 'Invalid OTP code'
    );

    $listener->handle($event);

    // Check that severity was escalated to 'high'
    $latestIncident = DB::table('abuse_incidents')
        ->where('email_hash', $emailHash)
        ->orderBy('created_at', 'desc')
        ->first();

    expect($latestIncident->severity)->toBe('high');
});

test('listener creates critical incident on IP mismatch', function () {
    $event = new SimpleUnblockIpMismatch(
        originalIp: '192.168.1.100',
        verificationIp: '10.0.0.50',
        email: 'test@example.com'
    );

    $listener = new CreateAbuseIncidentListener;
    $listener->handle($event);

    $emailHash = hash('sha256', 'test@example.com');

    assertDatabaseHas('abuse_incidents', [
        'incident_type' => 'ip_mismatch',
        'ip_address' => '10.0.0.50',
        'email_hash' => $emailHash,
        'severity' => 'critical',
    ]);
});

test('listener stores metadata as JSON', function () {
    $event = new SimpleUnblockRateLimitExceeded(
        vector: 'ip',
        identifier: '192.168.1.100',
        attempts: 15,
        maxAttempts: 10
    );

    $listener = new CreateAbuseIncidentListener;
    $listener->handle($event);

    $incident = AbuseIncident::where('ip_address', '192.168.1.100')->first();

    expect($incident->metadata)->toBeArray();
    expect($incident->metadata)->toHaveKey('vector');
    expect($incident->metadata)->toHaveKey('attempts');
    expect($incident->metadata['attempts'])->toBe(15);
});

test('listener decreases IP reputation on rate limit exceeded', function () {
    // Create IP reputation
    IpReputation::create([
        'ip' => '192.168.1.100',
        'subnet' => '192.168.1.0/24',
        'reputation_score' => 100,
        'total_requests' => 5,
        'failed_requests' => 0,
        'last_seen_at' => now(),
    ]);

    $event = new SimpleUnblockRateLimitExceeded(
        vector: 'ip',
        identifier: '192.168.1.100',
        attempts: 15,
        maxAttempts: 10
    );

    $listener = new CreateAbuseIncidentListener;
    $listener->handle($event);

    $reputation = IpReputation::where('ip', '192.168.1.100')->first();

    // Score should be decreased by 10
    expect($reputation->reputation_score)->toBe(90);
});

test('listener decreases email reputation on OTP failed', function () {
    $emailHash = hash('sha256', 'test@example.com');

    // Create email reputation
    EmailReputation::create([
        'email_hash' => $emailHash,
        'email_domain' => 'example.com',
        'reputation_score' => 100,
        'total_requests' => 5,
        'failed_requests' => 0,
        'verified_requests' => 3,
        'last_seen_at' => now(),
    ]);

    $event = new SimpleUnblockOtpFailed(
        email: 'test@example.com',
        ip: '192.168.1.100',
        reason: 'Invalid OTP code'
    );

    $listener = new CreateAbuseIncidentListener;
    $listener->handle($event);

    $reputation = EmailReputation::where('email_hash', $emailHash)->first();

    // Score should be decreased by 15 (medium severity)
    expect($reputation->reputation_score)->toBeLessThan(100);
});

test('listener applies heavy penalty on IP mismatch attack', function () {
    $emailHash = hash('sha256', 'test@example.com');

    // Create reputations
    IpReputation::create([
        'ip' => '10.0.0.50',
        'subnet' => '10.0.0.0/24',
        'reputation_score' => 100,
        'total_requests' => 5,
        'failed_requests' => 0,
        'last_seen_at' => now(),
    ]);

    EmailReputation::create([
        'email_hash' => $emailHash,
        'email_domain' => 'example.com',
        'reputation_score' => 100,
        'total_requests' => 5,
        'failed_requests' => 0,
        'verified_requests' => 3,
        'last_seen_at' => now(),
    ]);

    $event = new SimpleUnblockIpMismatch(
        originalIp: '192.168.1.100',
        verificationIp: '10.0.0.50',
        email: 'test@example.com'
    );

    $listener = new CreateAbuseIncidentListener;
    $listener->handle($event);

    $ipReputation = IpReputation::where('ip', '10.0.0.50')->first();
    $emailReputation = EmailReputation::where('email_hash', $emailHash)->first();

    // Both should be decreased by 40 (heavy penalty)
    expect($ipReputation->reputation_score)->toBe(60);
    expect($emailReputation->reputation_score)->toBe(60);
});

test('listener reputation score never goes below zero', function () {
    $emailHash = hash('sha256', 'test@example.com');

    // Create email with low score
    EmailReputation::create([
        'email_hash' => $emailHash,
        'email_domain' => 'example.com',
        'reputation_score' => 10,
        'total_requests' => 5,
        'failed_requests' => 4,
        'verified_requests' => 0,
        'last_seen_at' => now(),
    ]);

    $event = new SimpleUnblockIpMismatch(
        originalIp: '192.168.1.100',
        verificationIp: '10.0.0.50',
        email: 'test@example.com'
    );

    $listener = new CreateAbuseIncidentListener;
    $listener->handle($event);

    $reputation = EmailReputation::where('email_hash', $emailHash)->first();

    // Score should be 0, not negative
    expect($reputation->reputation_score)->toBe(0);
    expect($reputation->reputation_score)->toBeGreaterThanOrEqual(0);
});

test('listener is queued for async processing', function () {
    // Verify listener implements ShouldQueue
    $listener = new CreateAbuseIncidentListener;
    expect($listener)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

test('listener handles honeypot without email gracefully', function () {
    $event = new SimpleUnblockHoneypotTriggered(
        ip: '192.168.1.100',
        email: null,
        domain: null
    );

    $listener = new CreateAbuseIncidentListener;
    $listener->handle($event);

    assertDatabaseHas('abuse_incidents', [
        'incident_type' => 'honeypot_triggered',
        'ip_address' => '192.168.1.100',
        'email_hash' => null,
        'domain' => null,
        'severity' => 'medium',
    ]);
});
