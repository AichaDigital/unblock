<?php

declare(strict_types=1);

namespace App\Services\PatternDetection;

use App\Models\PatternDetection;
use Illuminate\Support\Facades\DB;

/**
 * Anomaly Detector
 *
 * Detects traffic spikes and anomalies using statistical analysis
 * Compares current traffic against historical baseline
 *
 * Algorithm:
 * - Calculate baseline: avg requests/hour (last 7 days)
 * - Calculate standard deviation
 * - Trigger: current > (baseline + 3σ)
 * - Sustained: 15+ minutes
 */
class AnomalyDetector
{
    /**
     * Baseline period in days
     */
    private const BASELINE_DAYS = 7;

    /**
     * Standard deviation multiplier (3σ = 99.7% confidence)
     */
    private const SIGMA_MULTIPLIER = 3;

    /**
     * Minimum sustained minutes to trigger
     */
    private const MIN_SUSTAINED_MINUTES = 15;

    /**
     * Detect traffic anomalies
     */
    public function detectAll(): array
    {
        $detections = [];

        // Calculate baseline
        $baseline = $this->calculateBaseline();

        if (! $baseline) {
            // Not enough historical data
            return [];
        }

        // Get current traffic
        $currentTraffic = $this->getCurrentTraffic();

        // Check if anomaly detected
        if ($this->isAnomaly($currentTraffic, $baseline)) {
            if ($detection = $this->createAnomalyDetection($currentTraffic, $baseline)) {
                $detections[] = $detection;
            }
        }

        return $detections;
    }

    /**
     * Calculate baseline traffic statistics
     */
    private function calculateBaseline(): ?array
    {
        // Get all reputation records for last N days (excluding today)
        // SQLite compatible: retrieve all data and group in PHP
        $data = DB::table('ip_reputation')
            ->select('last_seen_at', 'total_requests')
            ->where('last_seen_at', '>=', now()->subDays(self::BASELINE_DAYS))
            ->where('last_seen_at', '<', now()->startOfDay())
            ->get();

        if ($data->isEmpty()) {
            return null;
        }

        // Group by hour in PHP (SQLite compatible)
        $hourlyRequests = [];
        foreach ($data as $record) {
            $hour = date('Y-m-d H:00:00', strtotime($record->last_seen_at));
            if (! isset($hourlyRequests[$hour])) {
                $hourlyRequests[$hour] = 0;
            }
            $hourlyRequests[$hour] += $record->total_requests;
        }

        if (count($hourlyRequests) < 24) {
            // Need at least 24 hours of data
            return null;
        }

        $values = array_values($hourlyRequests);

        // Calculate mean
        $mean = array_sum($values) / count($values);

        // Calculate standard deviation
        $variance = array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $values)) / count($values);
        $stdDev = sqrt($variance);

        // Calculate threshold (mean + 3σ)
        $threshold = $mean + (self::SIGMA_MULTIPLIER * $stdDev);

        return [
            'mean' => round($mean, 2),
            'std_dev' => round($stdDev, 2),
            'threshold' => round($threshold, 2),
            'sample_size' => count($values),
        ];
    }

    /**
     * Get current traffic (last hour)
     */
    private function getCurrentTraffic(): int
    {
        return DB::table('ip_reputation')
            ->where('last_seen_at', '>=', now()->subHour())
            ->sum('total_requests');
    }

    /**
     * Check if current traffic is anomalous
     */
    private function isAnomaly(int $currentTraffic, array $baseline): bool
    {
        return $currentTraffic > $baseline['threshold'];
    }

    /**
     * Create anomaly detection record
     */
    private function createAnomalyDetection(int $currentTraffic, array $baseline): ?PatternDetection
    {
        // Check if already detected recently (avoid duplicate alerts)
        $existing = PatternDetection::where('pattern_type', PatternDetection::TYPE_ANOMALY_SPIKE)
            ->where('detected_at', '>=', now()->subMinutes(self::MIN_SUSTAINED_MINUTES))
            ->unresolved()
            ->first();

        if ($existing) {
            // Update existing detection
            $deviationMultiple = $baseline['std_dev'] > 0
                ? round(($currentTraffic - $baseline['mean']) / $baseline['std_dev'], 2)
                : 0;

            $existing->update([
                'last_incident_at' => now(),
                'pattern_data' => array_merge($existing->pattern_data ?? [], [
                    'current_traffic' => $currentTraffic,
                    'baseline' => $baseline,
                    'deviation_multiple' => $deviationMultiple,
                    'updated_at_timestamp' => now()->toDateTimeString(),
                ]),
            ]);

            return $existing;
        }

        // Calculate deviation multiple (handle zero std dev for uniform traffic)
        $deviationMultiple = $baseline['std_dev'] > 0
            ? round(($currentTraffic - $baseline['mean']) / $baseline['std_dev'], 2)
            : 0;

        // Calculate confidence score
        $confidence = $this->calculateConfidence($deviationMultiple);

        // Determine severity (anomalies are usually medium - could be legit traffic spike)
        $severity = $this->determineSeverity($deviationMultiple);

        // Create new detection
        $detection = PatternDetection::create([
            'pattern_type' => PatternDetection::TYPE_ANOMALY_SPIKE,
            'severity' => $severity,
            'confidence_score' => $confidence,
            'time_window_minutes' => 60,
            'detection_algorithm' => 'AnomalyDetector v1.0',
            'detected_at' => now(),
            'first_incident_at' => now()->subHour(),
            'last_incident_at' => now(),
            'pattern_data' => [
                'current_traffic' => $currentTraffic,
                'baseline_mean' => $baseline['mean'],
                'baseline_std_dev' => $baseline['std_dev'],
                'threshold' => $baseline['threshold'],
                'deviation_multiple' => $deviationMultiple,
                'sample_size' => $baseline['sample_size'],
            ],
        ]);

        return $detection;
    }

    /**
     * Calculate confidence score based on deviation
     */
    private function calculateConfidence(float $deviationMultiple): int
    {
        // 100%: 5σ+ deviation (extremely unlikely by chance)
        if ($deviationMultiple >= 5) {
            return 100;
        }

        // 75%: 4σ deviation
        if ($deviationMultiple >= 4) {
            return 75;
        }

        // 50%: 3σ deviation
        return 50;
    }

    /**
     * Determine severity (anomalies don't auto-escalate to critical)
     */
    private function determineSeverity(float $deviationMultiple): string
    {
        // High: Extreme deviation (5σ+)
        if ($deviationMultiple >= 5) {
            return PatternDetection::SEVERITY_HIGH;
        }

        // Medium: Normal anomaly threshold
        return PatternDetection::SEVERITY_MEDIUM;
    }
}
