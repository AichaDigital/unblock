<?php

declare(strict_types=1);

use App\Models\EmailReputation;

test('email reputation calculates verification rate correctly', function () {
    $reputation = new EmailReputation([
        'total_requests' => 100,
        'verified_requests' => 75,
        'failed_requests' => 25,
    ]);

    expect($reputation->verification_rate)->toBe(75.0);
});

test('email reputation returns zero verification rate when no requests', function () {
    $reputation = new EmailReputation([
        'total_requests' => 0,
        'verified_requests' => 0,
    ]);

    expect($reputation->verification_rate)->toBe(0.0);
});

test('email reputation calculates failure rate correctly', function () {
    $reputation = new EmailReputation([
        'total_requests' => 100,
        'failed_requests' => 30,
    ]);

    expect($reputation->failure_rate)->toBe(30.0);
});

test('email reputation returns zero failure rate when no requests', function () {
    $reputation = new EmailReputation([
        'total_requests' => 0,
        'failed_requests' => 0,
    ]);

    expect($reputation->failure_rate)->toBe(0.0);
});

test('email reputation returns success color for high score', function () {
    $reputation = new EmailReputation(['reputation_score' => 85]);

    expect($reputation->reputation_color)->toBe('success');
});

test('email reputation returns warning color for medium score', function () {
    $reputation = new EmailReputation(['reputation_score' => 60]);

    expect($reputation->reputation_color)->toBe('warning');
});

test('email reputation returns danger color for low score', function () {
    $reputation = new EmailReputation(['reputation_score' => 40]);

    expect($reputation->reputation_color)->toBe('danger');
});

test('email reputation truncates hash for display', function () {
    $reputation = new EmailReputation([
        'email_hash' => 'abcdef1234567890abcdef1234567890abcdef1234567890',
    ]);

    expect($reputation->truncated_hash)->toBe('abcdef1234567890...');
});

test('email reputation casts dates correctly', function () {
    $reputation = EmailReputation::create([
        'email_hash' => hash('sha256', 'test@example.com'),
        'email_domain' => 'example.com',
        'reputation_score' => 50,
        'total_requests' => 10,
        'failed_requests' => 2,
        'verified_requests' => 8,
        'last_seen_at' => now(),
    ]);

    expect($reputation->last_seen_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

test('email reputation has abuse incidents relationship', function () {
    $reputation = new EmailReputation;

    expect($reputation->abuseIncidents())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});
