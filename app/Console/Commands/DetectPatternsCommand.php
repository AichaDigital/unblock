<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PatternDetection\{AnomalyDetector, DistributedAttackDetector, SubnetScanDetector};
use Illuminate\Console\Command;

/**
 * Detect Attack Patterns Command
 *
 * Runs pattern detection algorithms to identify:
 * - Distributed attacks (same email, multiple IPs)
 * - Subnet scans (same subnet, multiple emails)
 * - Traffic anomalies (spikes)
 * - Coordinated attacks (temporal patterns)
 *
 * Scheduled: Hourly
 */
class DetectPatternsCommand extends Command
{
    protected $signature = 'patterns:detect
                            {--type= : Specific pattern type to detect (distributed, subnet, anomaly, coordinated)}
                            {--force : Skip confirmation}';

    protected $description = 'Detect attack patterns and security anomalies';

    public function handle(): int
    {
        $this->info('🔍 Starting pattern detection...');
        $this->newLine();

        $detections = [];

        // Run Distributed Attack Detector
        $this->info('→ Running Distributed Attack Detector...');
        $distributedDetector = new DistributedAttackDetector;
        $distributed = $distributedDetector->detectAll();
        $detections = array_merge($detections, $distributed);
        $count = count($distributed);
        $this->line("  Found {$count} distributed attack(s)");

        // Run Subnet Scan Detector
        $this->info('→ Running Subnet Scan Detector...');
        $subnetDetector = new SubnetScanDetector;
        $subnetScans = $subnetDetector->detectAll();
        $detections = array_merge($detections, $subnetScans);
        $count = count($subnetScans);
        $this->line("  Found {$count} subnet scan(s)");

        // Run Anomaly Detector
        $this->info('→ Running Anomaly Detector...');
        $anomalyDetector = new AnomalyDetector;
        $anomalies = $anomalyDetector->detectAll();
        $detections = array_merge($detections, $anomalies);
        $count = count($anomalies);
        $this->line("  Found {$count} traffic anomaly(ies)");

        $this->newLine();
        $this->info('✓ Detection complete! Total patterns found: '.count($detections));

        if (count($detections) > 0) {
            $this->table(
                ['Type', 'Severity', 'Confidence', 'Affected', 'Time'],
                collect($detections)->map(fn ($d) => [
                    $d->pattern_type_label,
                    strtoupper($d->severity),
                    $d->confidence_score.'%',
                    $d->affected_ips_count.' IPs',
                    $d->detected_at->diffForHumans(),
                ])->all()
            );
        }

        return self::SUCCESS;
    }
}
