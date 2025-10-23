<?php

declare(strict_types=1);

use App\Models\{IpReputation, PatternDetection};
use App\Services\PatternDetection\SubnetScanDetector;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\assertDatabaseCount;

beforeEach(function () {
    $this->artisan('migrate');
});

test('detector identifies subnet scan with 10+ different emails', function () {
    // Create incidents from same subnet with 10 different emails
    for ($i = 1; $i <= 10; $i++) {
        DB::table('abuse_incidents')->insert([
            'incident_type' => 'rate_limit_exceeded',
            'ip_address' => "192.168.1.{$i}",
            'email_hash' => hash('sha256', "user{$i}@example.com"),
            'severity' => 'medium',
            'description' => 'Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $detector = new SubnetScanDetector;
    $detection = $detector->detectForSubnet('192.168.1');

    expect($detection)->not()->toBeNull();
    expect($detection->pattern_type)->toBe(PatternDetection::TYPE_SUBNET_SCAN);
    expect($detection->affected_emails_count)->toBe(10);
    expect($detection->subnet)->toBe('192.168.1.0/24');
});

test('detector does not trigger with fewer than 10 emails', function () {
    // Only 9 different emails
    for ($i = 1; $i <= 9; $i++) {
        DB::table('abuse_incidents')->insert([
            'incident_type' => 'rate_limit_exceeded',
            'ip_address' => "192.168.1.{$i}",
            'email_hash' => hash('sha256', "user{$i}@example.com"),
            'severity' => 'medium',
            'description' => 'Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $detector = new SubnetScanDetector;
    $detection = $detector->detectForSubnet('192.168.1');

    expect($detection)->toBeNull();
});

test('detector calculates 50% confidence for 10-14 emails', function () {
    for ($i = 1; $i <= 12; $i++) {
        DB::table('abuse_incidents')->insert([
            'incident_type' => 'rate_limit_exceeded',
            'ip_address' => "192.168.1.{$i}",
            'email_hash' => hash('sha256', "user{$i}@example.com"),
            'severity' => 'medium',
            'description' => 'Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $detector = new SubnetScanDetector;
    $detection = $detector->detectForSubnet('192.168.1');

    expect($detection->confidence_score)->toBe(50);
    expect($detection->severity)->toBe(PatternDetection::SEVERITY_MEDIUM);
});

test('detector calculates 75% confidence for 15-19 emails', function () {
    for ($i = 1; $i <= 16; $i++) {
        DB::table('abuse_incidents')->insert([
            'incident_type' => 'rate_limit_exceeded',
            'ip_address' => "192.168.1.{$i}",
            'email_hash' => hash('sha256', "user{$i}@example.com"),
            'severity' => 'medium',
            'description' => 'Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $detector = new SubnetScanDetector;
    $detection = $detector->detectForSubnet('192.168.1');

    expect($detection->confidence_score)->toBe(75);
});

test('detector calculates 100% confidence for 20+ emails', function () {
    for ($i = 1; $i <= 25; $i++) {
        DB::table('abuse_incidents')->insert([
            'incident_type' => 'rate_limit_exceeded',
            'ip_address' => "192.168.1.{$i}",
            'email_hash' => hash('sha256', "user{$i}@example.com"),
            'severity' => 'medium',
            'description' => 'Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $detector = new SubnetScanDetector;
    $detection = $detector->detectForSubnet('192.168.1');

    expect($detection->confidence_score)->toBe(100);
    expect($detection->severity)->toBe(PatternDetection::SEVERITY_HIGH);
});

test('detector applies penalties to IPs in subnet', function () {
    // Create IP reputations
    IpReputation::create(['ip' => '192.168.1.10', 'subnet' => '192.168.1.0/24', 'reputation_score' => 100, 'total_requests' => 5, 'failed_requests' => 0, 'last_seen_at' => now()]);
    IpReputation::create(['ip' => '192.168.1.20', 'subnet' => '192.168.1.0/24', 'reputation_score' => 100, 'total_requests' => 5, 'failed_requests' => 0, 'last_seen_at' => now()]);

    // Create incidents
    for ($i = 1; $i <= 10; $i++) {
        DB::table('abuse_incidents')->insert([
            'incident_type' => 'rate_limit_exceeded',
            'ip_address' => "192.168.1.{$i}",
            'email_hash' => hash('sha256', "user{$i}@example.com"),
            'severity' => 'medium',
            'description' => 'Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $detector = new SubnetScanDetector;
    $detector->detectForSubnet('192.168.1');

    // Check IP penalties applied
    $ip1Rep = IpReputation::where('ip', '192.168.1.10')->first();
    expect($ip1Rep->reputation_score)->toBeLessThan(100);
});

test('detector prevents duplicate detections', function () {
    for ($i = 1; $i <= 10; $i++) {
        DB::table('abuse_incidents')->insert([
            'incident_type' => 'rate_limit_exceeded',
            'ip_address' => "192.168.1.{$i}",
            'email_hash' => hash('sha256', "user{$i}@example.com"),
            'severity' => 'medium',
            'description' => 'Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $detector = new SubnetScanDetector;

    // First detection
    $detection1 = $detector->detectForSubnet('192.168.1');

    // Second detection (should update existing)
    $detection2 = $detector->detectForSubnet('192.168.1');

    expect($detection1->id)->toBe($detection2->id);
    assertDatabaseCount('pattern_detections', 1);
});
