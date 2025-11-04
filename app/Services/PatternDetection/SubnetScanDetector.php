<?php

declare(strict_types=1);

namespace App\Services\PatternDetection;

use App\Models\{AbuseIncident, IpReputation, PatternDetection};
use Illuminate\Support\Facades\DB;

/**
 * Subnet Scan Detector
 *
 * Detects when same subnet attacks with multiple different emails
 * Indicates scanning/enumeration attack or compromised subnet
 *
 * Algorithm:
 * - Same /24 subnet
 * - 10+ different email_hashes
 * - Within 120 minutes window
 */
class SubnetScanDetector
{
    /**
     * Minimum emails to trigger detection
     */
    private const MIN_EMAILS = 10;

    /**
     * Time window in minutes
     */
    private const TIME_WINDOW = 120;

    /**
     * Detect subnet scans for all subnets with recent activity
     */
    public function detectAll(): array
    {
        $detections = [];

        // Get recent incidents with multiple emails
        // Extract subnet prefix in PHP to be database-agnostic
        $recentIncidents = DB::table('abuse_incidents')
            ->select('ip_address', 'email_hash')
            ->where('created_at', '>=', now()->subMinutes(self::TIME_WINDOW))
            ->whereNotNull('email_hash')
            ->get();

        // Group by subnet prefix in PHP (database-agnostic approach)
        $subnetGroups = $recentIncidents->groupBy(function ($incident) {
            return $this->extractSubnetPrefix($incident->ip_address);
        });

        // Filter subnets with enough unique emails
        $recentSubnets = $subnetGroups
            ->filter(function ($incidents) {
                return $incidents->pluck('email_hash')->unique()->count() >= self::MIN_EMAILS;
            })
            ->keys();

        foreach ($recentSubnets as $subnetPrefix) {
            if ($detection = $this->detectForSubnet($subnetPrefix)) {
                $detections[] = $detection;
            }
        }

        return $detections;
    }

    /**
     * Extract subnet prefix from IP address (database-agnostic)
     * Returns first 3 octets for IPv4 (e.g., "192.168.1" from "192.168.1.100")
     */
    private function extractSubnetPrefix(string $ipAddress): string
    {
        // IPv4
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ipAddress);

            return implode('.', array_slice($parts, 0, 3));
        }

        // IPv6 - take first 3 segments
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ipAddress);

            return implode(':', array_slice($parts, 0, 3));
        }

        // Fallback
        return $ipAddress;
    }

    /**
     * Detect subnet scan for specific subnet prefix
     */
    public function detectForSubnet(string $subnetPrefix): ?PatternDetection
    {
        // Get recent incidents from this subnet
        $incidents = AbuseIncident::where('ip_address', 'like', $subnetPrefix.'%')
            ->where('created_at', '>=', now()->subMinutes(self::TIME_WINDOW))
            ->get();

        if ($incidents->count() < self::MIN_EMAILS) {
            return null;
        }

        // Extract unique emails and IPs
        $uniqueEmails = $incidents->pluck('email_hash')->filter()->unique();
        $uniqueIps = $incidents->pluck('ip_address')->unique();

        // Check if criteria met
        if ($uniqueEmails->count() < self::MIN_EMAILS) {
            return null;
        }

        // Calculate full subnet notation
        $subnet = $this->formatSubnet($subnetPrefix);

        // Calculate confidence score
        $confidence = $this->calculateConfidence($uniqueEmails->count());

        // Determine severity
        $severity = $this->determineSeverity($confidence, $uniqueEmails->count());

        // Check if already detected (avoid duplicates)
        $existing = PatternDetection::where('pattern_type', PatternDetection::TYPE_SUBNET_SCAN)
            ->where('subnet', $subnet)
            ->where('detected_at', '>=', now()->subHours(2))
            ->unresolved()
            ->first();

        if ($existing) {
            // Update existing detection
            $existing->update([
                'affected_emails_count' => $uniqueEmails->count(),
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
            'pattern_type' => PatternDetection::TYPE_SUBNET_SCAN,
            'severity' => $severity,
            'confidence_score' => $confidence,
            'subnet' => $subnet,
            'affected_emails_count' => $uniqueEmails->count(),
            'affected_ips_count' => $uniqueIps->count(),
            'time_window_minutes' => self::TIME_WINDOW,
            'detection_algorithm' => 'SubnetScanDetector v1.0',
            'detected_at' => now(),
            'first_incident_at' => $incidents->min('created_at'),
            'last_incident_at' => $incidents->max('created_at'),
            'pattern_data' => [
                'unique_emails' => $uniqueEmails->values()->take(20)->toArray(), // Limit to 20 for storage
                'unique_ips' => $uniqueIps->values()->toArray(),
                'incident_count' => $incidents->count(),
            ],
            'related_incidents' => $incidents->pluck('id')->toArray(),
        ]);

        // Apply penalties
        $this->applyPenalties($uniqueIps, $severity);

        return $detection;
    }

    /**
     * Format subnet with CIDR notation
     */
    private function formatSubnet(string $subnetPrefix): string
    {
        // Remove trailing dot if present
        $subnetPrefix = rtrim($subnetPrefix, '.');

        // IPv4
        if (substr_count($subnetPrefix, '.') === 2) {
            return $subnetPrefix.'.0/24';
        }

        // IPv6 (simplified)
        if (str_contains($subnetPrefix, ':')) {
            return $subnetPrefix.'::/48';
        }

        return $subnetPrefix.'.0/24';
    }

    /**
     * Calculate confidence score (0-100)
     */
    private function calculateConfidence(int $emailCount): int
    {
        // 100%: 20+ emails
        if ($emailCount >= 20) {
            return 100;
        }

        // 75%: 15-19 emails
        if ($emailCount >= 15) {
            return 75;
        }

        // 50%: 10-14 emails
        return 50;
    }

    /**
     * Determine severity based on confidence and scale
     */
    private function determineSeverity(int $confidence, int $emailCount): string
    {
        if ($confidence === 100 || $emailCount >= 25) {
            return PatternDetection::SEVERITY_HIGH;
        }

        return PatternDetection::SEVERITY_MEDIUM;
    }

    /**
     * Apply reputation penalties to IPs in subnet
     */
    private function applyPenalties($uniqueIps, string $severity): void
    {
        // Subnet scan indicates compromised network or malicious actor
        $ipPenalty = match ($severity) {
            PatternDetection::SEVERITY_HIGH => 25,
            default => 20,
        };

        foreach ($uniqueIps as $ip) {
            $ipRep = IpReputation::where('ip', $ip)->first();
            if ($ipRep) {
                $newScore = max(0, $ipRep->reputation_score - $ipPenalty);
                $ipRep->update(['reputation_score' => $newScore]);
            }
        }
    }
}
