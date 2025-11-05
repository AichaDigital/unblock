<?php

declare(strict_types=1);

use App\Models\IpReputation;

test('ip reputation calculates success rate correctly', function () {
    $reputation = new IpReputation([
        'total_requests' => 100,
        'failed_requests' => 20,
    ]);

    expect($reputation->success_rate)->toBe(80.0);
});

test('ip reputation returns 100% success rate when no requests', function () {
    $reputation = new IpReputation([
        'total_requests' => 0,
        'failed_requests' => 0,
    ]);

    expect($reputation->success_rate)->toBe(100.0);
});

test('ip reputation returns success color for high score', function () {
    $reputation = new IpReputation(['reputation_score' => 90]);

    expect($reputation->reputation_color)->toBe('success');
});

test('ip reputation returns warning color for medium score', function () {
    $reputation = new IpReputation(['reputation_score' => 65]);

    expect($reputation->reputation_color)->toBe('warning');
});

test('ip reputation returns danger color for low score', function () {
    $reputation = new IpReputation(['reputation_score' => 30]);

    expect($reputation->reputation_color)->toBe('danger');
});

test('ip reputation casts geo coordinates correctly', function () {
    $reputation = IpReputation::create([
        'ip' => '192.168.1.1',
        'subnet' => '192.168.1.0/24',
        'reputation_score' => 50,
        'total_requests' => 10,
        'failed_requests' => 2,
        'blocked_count' => 1,
        'latitude' => 40.7128,
        'longitude' => -74.0060,
        'last_seen_at' => now(),
    ]);

    expect($reputation->latitude)->toBeFloat()
        ->and($reputation->longitude)->toBeFloat()
        ->and($reputation->last_seen_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

test('ip reputation has abuse incidents relationship', function () {
    $reputation = new IpReputation;

    expect($reputation->abuseIncidents())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('ip reputation stores geo location data', function () {
    $reputation = IpReputation::create([
        'ip' => '8.8.8.8',
        'subnet' => '8.8.8.0/24',
        'reputation_score' => 100,
        'total_requests' => 100,
        'failed_requests' => 0,
        'blocked_count' => 0,
        'country_code' => 'US',
        'country_name' => 'United States',
        'city' => 'Mountain View',
        'postal_code' => '94043',
        'timezone' => 'America/Los_Angeles',
        'continent' => 'North America',
        'last_seen_at' => now(),
    ]);

    expect($reputation->country_code)->toBe('US')
        ->and($reputation->country_name)->toBe('United States')
        ->and($reputation->city)->toBe('Mountain View')
        ->and($reputation->timezone)->toBe('America/Los_Angeles');
});
