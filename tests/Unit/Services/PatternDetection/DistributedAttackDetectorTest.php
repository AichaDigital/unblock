<?php

declare(strict_types=1);

use App\Models\{EmailReputation, IpReputation, PatternDetection};
use App\Services\PatternDetection\DistributedAttackDetector;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\{assertDatabaseCount};

beforeEach(function () {
    $this->artisan('migrate');
});

test('detector identifies distributed attack with 5+ IPs and 3+ subnets', function () {
    $emailHash = hash('sha256', 'attacker@evil.com');

    // Create incidents from 5 different IPs in 3 different subnets
    DB::table('abuse_incidents')->insert([
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.1.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.1.20', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.2.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.2.20', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.3.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $detector = new DistributedAttackDetector;
    $detection = $detector->detectForEmail($emailHash);

    expect($detection)->not()->toBeNull();
    expect($detection->pattern_type)->toBe(PatternDetection::TYPE_DISTRIBUTED_ATTACK);
    expect($detection->affected_ips_count)->toBe(5);
    expect($detection->email_hash)->toBe($emailHash);
});

test('detector does not trigger with fewer than 5 IPs', function () {
    $emailHash = hash('sha256', 'test@example.com');

    // Only 4 IPs
    DB::table('abuse_incidents')->insert([
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.1.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.2.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.3.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.4.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $detector = new DistributedAttackDetector;
    $detection = $detector->detectForEmail($emailHash);

    expect($detection)->toBeNull();
});

test('detector does not trigger with fewer than 3 subnets', function () {
    $emailHash = hash('sha256', 'test@example.com');

    // 5 IPs but only 2 subnets
    DB::table('abuse_incidents')->insert([
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.1.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.1.20', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.1.30', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.2.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.2.20', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $detector = new DistributedAttackDetector;
    $detection = $detector->detectForEmail($emailHash);

    expect($detection)->toBeNull();
});

test('detector calculates 50% confidence for 5-6 IPs', function () {
    $emailHash = hash('sha256', 'test@example.com');

    // 5 IPs, 3 subnets
    DB::table('abuse_incidents')->insert([
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.1.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.2.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.3.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.4.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.5.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $detector = new DistributedAttackDetector;
    $detection = $detector->detectForEmail($emailHash);

    expect($detection->confidence_score)->toBe(50);
});

test('detector calculates 75% confidence for 7-9 IPs', function () {
    $emailHash = hash('sha256', 'test@example.com');

    // 7 IPs, 4 subnets
    DB::table('abuse_incidents')->insert([
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.1.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.2.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.3.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.4.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.5.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.6.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.7.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $detector = new DistributedAttackDetector;
    $detection = $detector->detectForEmail($emailHash);

    expect($detection->confidence_score)->toBe(75);
});

test('detector calculates 100% confidence for 10+ IPs', function () {
    $emailHash = hash('sha256', 'test@example.com');

    // 10 IPs, 5 subnets
    for ($i = 1; $i <= 10; $i++) {
        DB::table('abuse_incidents')->insert([
            'incident_type' => 'rate_limit_exceeded',
            'ip_address' => "192.168.{$i}.10",
            'email_hash' => $emailHash,
            'severity' => 'medium',
            'description' => 'Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $detector = new DistributedAttackDetector;
    $detection = $detector->detectForEmail($emailHash);

    expect($detection->confidence_score)->toBe(100);
    expect($detection->severity)->toBe(PatternDetection::SEVERITY_CRITICAL);
});

test('detector applies penalties to email and IPs', function () {
    $emailHash = hash('sha256', 'attacker@evil.com');

    // Create email reputation
    EmailReputation::create([
        'email_hash' => $emailHash,
        'email_domain' => 'evil.com',
        'reputation_score' => 100,
        'total_requests' => 10,
        'failed_requests' => 0,
        'verified_requests' => 5,
        'last_seen_at' => now(),
    ]);

    // Create IP reputations
    IpReputation::create(['ip' => '192.168.1.10', 'subnet' => '192.168.1.0/24', 'reputation_score' => 100, 'total_requests' => 5, 'failed_requests' => 0, 'last_seen_at' => now()]);
    IpReputation::create(['ip' => '192.168.2.10', 'subnet' => '192.168.2.0/24', 'reputation_score' => 100, 'total_requests' => 5, 'failed_requests' => 0, 'last_seen_at' => now()]);

    // Create incidents
    DB::table('abuse_incidents')->insert([
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.1.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.2.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.3.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.4.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '192.168.5.10', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $detector = new DistributedAttackDetector;
    $detector->detectForEmail($emailHash);

    // Check email penalty applied
    $emailRep = EmailReputation::where('email_hash', $emailHash)->first();
    expect($emailRep->reputation_score)->toBeLessThan(100);

    // Check IP penalties applied
    $ip1Rep = IpReputation::where('ip', '192.168.1.10')->first();
    expect($ip1Rep->reputation_score)->toBeLessThan(100);
});

test('detector prevents duplicate detections', function () {
    $emailHash = hash('sha256', 'test@example.com');

    // Create incidents
    for ($i = 1; $i <= 5; $i++) {
        DB::table('abuse_incidents')->insert([
            'incident_type' => 'rate_limit_exceeded',
            'ip_address' => "192.168.{$i}.10",
            'email_hash' => $emailHash,
            'severity' => 'medium',
            'description' => 'Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $detector = new DistributedAttackDetector;

    // First detection
    $detection1 = $detector->detectForEmail($emailHash);

    // Second detection (should update existing)
    $detection2 = $detector->detectForEmail($emailHash);

    expect($detection1->id)->toBe($detection2->id);
    assertDatabaseCount('pattern_detections', 1);
});

test('detector handles IPv6 addresses', function () {
    $emailHash = hash('sha256', 'test@example.com');

    // Create incidents with IPv6
    DB::table('abuse_incidents')->insert([
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '2001:0db8:85a3::1', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '2001:0db8:85a4::1', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '2001:0db8:85a5::1', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '2001:0db8:85a6::1', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['incident_type' => 'rate_limit_exceeded', 'ip_address' => '2001:0db8:85a7::1', 'email_hash' => $emailHash, 'severity' => 'medium', 'description' => 'Test', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $detector = new DistributedAttackDetector;
    $detection = $detector->detectForEmail($emailHash);

    expect($detection)->not()->toBeNull();
    expect($detection->affected_ips_count)->toBe(5);
});
