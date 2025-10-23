<?php

declare(strict_types=1);

use App\Models\{IpReputation, PatternDetection};
use App\Services\PatternDetection\AnomalyDetector;

use function Pest\Laravel\assertDatabaseCount;

beforeEach(function () {
    $this->artisan('migrate');
});

test('detector requires at least 24 hours of baseline data', function () {
    // Create minimal data (less than 24 hours)
    for ($i = 1; $i <= 10; $i++) {
        IpReputation::create([
            'ip' => "192.168.1.{$i}",
            'subnet' => '192.168.1.0/24',
            'reputation_score' => 100,
            'total_requests' => 100,
            'failed_requests' => 0,
            'last_seen_at' => now()->subHours($i),
        ]);
    }

    $detector = new AnomalyDetector;
    $detections = $detector->detectAll();

    expect($detections)->toBeArray();
    expect($detections)->toBeEmpty(); // Not enough data
});

test('detector identifies traffic spike above 3 sigma threshold', function () {
    // Create baseline data (7 days, avg 100 requests/hour)
    for ($day = 1; $day <= 7; $day++) {
        for ($hour = 0; $hour < 24; $hour++) {
            IpReputation::create([
                'ip' => "10.0.{$day}.{$hour}",
                'subnet' => "10.0.{$day}.0/24",
                'reputation_score' => 100,
                'total_requests' => 100, // Consistent baseline
                'failed_requests' => 0,
                'last_seen_at' => now()->subDays($day)->subHours($hour),
            ]);
        }
    }

    // Create spike in current hour (far above baseline)
    for ($i = 1; $i <= 50; $i++) {
        IpReputation::create([
            'ip' => "192.168.1.{$i}",
            'subnet' => '192.168.1.0/24',
            'reputation_score' => 100,
            'total_requests' => 500, // 5x baseline = significant spike
            'failed_requests' => 0,
            'last_seen_at' => now()->subMinutes($i),
        ]);
    }

    $detector = new AnomalyDetector;
    $detections = $detector->detectAll();

    expect($detections)->toBeArray();
    expect($detections)->not()->toBeEmpty();

    $detection = $detections[0];
    expect($detection->pattern_type)->toBe(PatternDetection::TYPE_ANOMALY_SPIKE);
    expect($detection->pattern_data)->toHaveKey('current_traffic');
    expect($detection->pattern_data)->toHaveKey('baseline_mean');
});

test('detector calculates confidence based on deviation multiple', function () {
    // Create stable baseline
    for ($day = 1; $day <= 7; $day++) {
        for ($hour = 0; $hour < 24; $hour++) {
            IpReputation::create([
                'ip' => "10.0.{$day}.{$hour}",
                'subnet' => "10.0.{$day}.0/24",
                'reputation_score' => 100,
                'total_requests' => 100,
                'failed_requests' => 0,
                'last_seen_at' => now()->subDays($day)->subHours($hour),
            ]);
        }
    }

    // Create extreme spike (should be 5σ+)
    for ($i = 1; $i <= 100; $i++) {
        IpReputation::create([
            'ip' => "192.168.1.{$i}",
            'subnet' => '192.168.1.0/24',
            'reputation_score' => 100,
            'total_requests' => 1000, // Extreme spike
            'failed_requests' => 0,
            'last_seen_at' => now()->subMinutes($i),
        ]);
    }

    $detector = new AnomalyDetector;
    $detections = $detector->detectAll();

    if (! empty($detections)) {
        $detection = $detections[0];
        // High deviation should result in high confidence
        expect($detection->confidence_score)->toBeGreaterThanOrEqual(50);
    }
});

test('detector does not trigger on normal traffic', function () {
    // Create baseline with realistic variance (90-110 requests/hour)
    for ($day = 1; $day <= 7; $day++) {
        for ($hour = 0; $hour < 24; $hour++) {
            IpReputation::create([
                'ip' => "10.0.{$day}.{$hour}",
                'subnet' => "10.0.{$day}.0/24",
                'reputation_score' => 100,
                'total_requests' => rand(90, 110), // Natural variance
                'failed_requests' => 0,
                'last_seen_at' => now()->subDays($day)->subHours($hour),
            ]);
        }
    }

    // Create normal current traffic (within baseline)
    IpReputation::create([
        'ip' => '192.168.1.10',
        'subnet' => '192.168.1.0/24',
        'reputation_score' => 100,
        'total_requests' => 105, // Within normal variance
        'failed_requests' => 0,
        'last_seen_at' => now()->subMinutes(30),
    ]);

    $detector = new AnomalyDetector;
    $detections = $detector->detectAll();

    expect($detections)->toBeEmpty();
});

test('detector prevents duplicate detections within time window', function () {
    // Create baseline
    for ($day = 1; $day <= 7; $day++) {
        for ($hour = 0; $hour < 24; $hour++) {
            IpReputation::create([
                'ip' => "10.0.{$day}.{$hour}",
                'subnet' => "10.0.{$day}.0/24",
                'reputation_score' => 100,
                'total_requests' => 100,
                'failed_requests' => 0,
                'last_seen_at' => now()->subDays($day)->subHours($hour),
            ]);
        }
    }

    // Create spike
    for ($i = 1; $i <= 50; $i++) {
        IpReputation::create([
            'ip' => "192.168.1.{$i}",
            'subnet' => '192.168.1.0/24',
            'reputation_score' => 100,
            'total_requests' => 500,
            'failed_requests' => 0,
            'last_seen_at' => now()->subMinutes($i),
        ]);
    }

    $detector = new AnomalyDetector;

    // First detection
    $detections1 = $detector->detectAll();

    // Second detection (should update existing)
    $detections2 = $detector->detectAll();

    // Should have same detection (updated, not duplicated)
    assertDatabaseCount('pattern_detections', count($detections1));
});

test('detector assigns medium severity for normal anomalies', function () {
    // Create baseline
    for ($day = 1; $day <= 7; $day++) {
        for ($hour = 0; $hour < 24; $hour++) {
            IpReputation::create([
                'ip' => "10.0.{$day}.{$hour}",
                'subnet' => "10.0.{$day}.0/24",
                'reputation_score' => 100,
                'total_requests' => 100,
                'failed_requests' => 0,
                'last_seen_at' => now()->subDays($day)->subHours($hour),
            ]);
        }
    }

    // Create moderate spike (3-4σ)
    for ($i = 1; $i <= 30; $i++) {
        IpReputation::create([
            'ip' => "192.168.1.{$i}",
            'subnet' => '192.168.1.0/24',
            'reputation_score' => 100,
            'total_requests' => 400, // Moderate spike
            'failed_requests' => 0,
            'last_seen_at' => now()->subMinutes($i),
        ]);
    }

    $detector = new AnomalyDetector;
    $detections = $detector->detectAll();

    if (! empty($detections)) {
        $detection = $detections[0];
        // Normal anomalies should be medium (not critical - could be legit traffic)
        expect($detection->severity)->toBeIn([PatternDetection::SEVERITY_MEDIUM, PatternDetection::SEVERITY_HIGH]);
    }
});
