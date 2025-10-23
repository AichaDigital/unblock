<?php

declare(strict_types=1);

namespace App\Services\PatternDetection;

use App\Models\{AbuseIncident, EmailReputation, IpReputation, PatternDetection};
use Illuminate\Support\Facades\DB;

/**
 * Distributed Attack Detector
 *
 * Detects when same email hash attacks from multiple IPs (different subnets)
 * Indicates botnet or coordinated distributed attack
 *
 * Algorithm:
 * - Same email_hash
 * - 5+ different IPs
 * - 3+ different /24 subnets
 * - Within 60 minutes window
 */
class DistributedAttackDetector
{
    /**
     * Minimum IPs to trigger detection
     */
    private const MIN_IPS = 5;

    /**
     * Minimum subnets to trigger detection
     */
    private const MIN_SUBNETS = 3;

    /**
     * Time window in minutes
     */
    private const TIME_WINDOW = 60;

    /**
     * Detect distributed attacks for all emails with recent activity
     */
    public function detectAll(): array
    {
        $detections = [];

        // Get emails with recent incidents
        $recentEmailHashes = DB::table('abuse_incidents')
            ->select('email_hash')
            ->where('created_at', '>=', now()->subMinutes(self::TIME_WINDOW))
            ->whereNotNull('email_hash')
            ->distinct()
            ->pluck('email_hash');

        foreach ($recentEmailHashes as $emailHash) {
            if ($detection = $this->detectForEmail($emailHash)) {
                $detections[] = $detection;
            }
        }

        return $detections;
    }

    /**
     * Detect distributed attack for specific email hash
     */
    public function detectForEmail(string $emailHash): ?PatternDetection
    {
        // Get recent incidents for this email
        $incidents = AbuseIncident::where('email_hash', $emailHash)
            ->where('created_at', '>=', now()->subMinutes(self::TIME_WINDOW))
            ->get();

        if ($incidents->count() < self::MIN_IPS) {
            return null;
        }

        // Extract unique IPs and subnets
        $uniqueIps = $incidents->pluck('ip_address')->unique();
        $uniqueSubnets = $uniqueIps->map(fn ($ip) => $this->calculateSubnet($ip))->unique();

        // Check if criteria met
        if ($uniqueIps->count() < self::MIN_IPS || $uniqueSubnets->count() < self::MIN_SUBNETS) {
            return null;
        }

        // Calculate confidence score
        $confidence = $this->calculateConfidence($uniqueIps->count(), $uniqueSubnets->count());

        // Determine severity
        $severity = $this->determineSeverity($confidence, $uniqueIps->count());

        // Check if already detected (avoid duplicates)
        $existing = PatternDetection::where('pattern_type', PatternDetection::TYPE_DISTRIBUTED_ATTACK)
            ->where('email_hash', $emailHash)
            ->where('detected_at', '>=', now()->subHour())
            ->unresolved()
            ->first();

        if ($existing) {
            // Update existing detection
            $existing->update([
                'affected_ips_count' => $uniqueIps->count(),
                'confidence_score' => $confidence,
                'severity' => $severity,
                'last_incident_at' => now(),
                'related_incidents' => $incidents->pluck('id')->toArray(),
            ]);

            return $existing;
        }

        // Create new pattern detection
        $detection = PatternDetection::create([
            'pattern_type' => PatternDetection::TYPE_DISTRIBUTED_ATTACK,
            'severity' => $severity,
            'confidence_score' => $confidence,
            'email_hash' => $emailHash,
            'affected_ips_count' => $uniqueIps->count(),
            'time_window_minutes' => self::TIME_WINDOW,
            'detection_algorithm' => 'DistributedAttackDetector v1.0',
            'detected_at' => now(),
            'first_incident_at' => $incidents->min('created_at'),
            'last_incident_at' => $incidents->max('created_at'),
            'pattern_data' => [
                'unique_ips' => $uniqueIps->values()->toArray(),
                'unique_subnets' => $uniqueSubnets->values()->toArray(),
                'incident_count' => $incidents->count(),
            ],
            'related_incidents' => $incidents->pluck('id')->toArray(),
        ]);

        // Apply penalties
        $this->applyPenalties($emailHash, $uniqueIps, $severity);

        return $detection;
    }

    /**
     * Calculate subnet from IP address
     */
    private function calculateSubnet(string $ip): string
    {
        // IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);

            return "{$parts[0]}.{$parts[1]}.{$parts[2]}.0/24";
        }

        // IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);

            return "{$parts[0]}:{$parts[1]}:{$parts[2]}::/48";
        }

        return 'unknown';
    }

    /**
     * Calculate confidence score (0-100)
     */
    private function calculateConfidence(int $ipCount, int $subnetCount): int
    {
        // 100%: 10+ IPs, 5+ subnets
        if ($ipCount >= 10 && $subnetCount >= 5) {
            return 100;
        }

        // 75%: 7-9 IPs, 3-4 subnets
        if ($ipCount >= 7 && $subnetCount >= 3) {
            return 75;
        }

        // 50%: 5-6 IPs, 3 subnets
        return 50;
    }

    /**
     * Determine severity based on confidence and scale
     */
    private function determineSeverity(int $confidence, int $ipCount): string
    {
        if ($confidence === 100 || $ipCount >= 15) {
            return PatternDetection::SEVERITY_CRITICAL;
        }

        if ($confidence >= 75 || $ipCount >= 10) {
            return PatternDetection::SEVERITY_HIGH;
        }

        return PatternDetection::SEVERITY_MEDIUM;
    }

    /**
     * Apply reputation penalties
     */
    private function applyPenalties(string $emailHash, $uniqueIps, string $severity): void
    {
        // Email penalty (heavy - confirmed distributed attack)
        $emailPenalty = match ($severity) {
            PatternDetection::SEVERITY_CRITICAL => 50,
            PatternDetection::SEVERITY_HIGH => 40,
            default => 30,
        };

        // SQLite compatible: Get current score and calculate new score
        $emailRep = EmailReputation::where('email_hash', $emailHash)->first();
        if ($emailRep) {
            $newScore = max(0, $emailRep->reputation_score - $emailPenalty);
            $emailRep->update(['reputation_score' => $newScore]);
        }

        // IP penalties (lighter - could be victims/bots)
        $ipPenalty = 15;

        foreach ($uniqueIps as $ip) {
            $ipRep = IpReputation::where('ip', $ip)->first();
            if ($ipRep) {
                $newScore = max(0, $ipRep->reputation_score - $ipPenalty);
                $ipRep->update(['reputation_score' => $newScore]);
            }
        }
    }
}
