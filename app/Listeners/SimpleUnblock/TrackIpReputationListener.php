<?php

declare(strict_types=1);

namespace App\Listeners\SimpleUnblock;

use App\Events\SimpleUnblock\SimpleUnblockRequestProcessed;
use App\Services\GeoIPService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

/**
 * Tracks IP reputation based on Simple Unblock requests
 *
 * Updates ip_reputation table with request statistics
 * Calculates reputation score based on success/failure ratio
 * Enriches IP data with geographic information
 */
class TrackIpReputationListener implements ShouldQueue
{
    public function __construct(
        private GeoIPService $geoIpService
    ) {}

    public function handle(SimpleUnblockRequestProcessed $event): void
    {
        $subnet = $this->calculateSubnet($event->ip);
        $geoData = $this->geoIpService->lookup($event->ip);

        // Check if record exists
        $exists = DB::table('ip_reputation')->where('ip', $event->ip)->exists();

        if ($exists) {
            // Update existing record
            $updateData = [
                'subnet' => $subnet,
                'total_requests' => DB::raw('total_requests + 1'),
                'failed_requests' => DB::raw($event->success ? 'failed_requests' : 'failed_requests + 1'),
                'last_seen_at' => now(),
                'updated_at' => now(),
            ];

            // Add geo data if available and not already set
            if ($geoData) {
                $existing = DB::table('ip_reputation')->where('ip', $event->ip)->first();
                if (! $existing->country_code) {
                    $updateData = array_merge($updateData, $geoData);
                }
            }

            DB::table('ip_reputation')
                ->where('ip', $event->ip)
                ->update($updateData);
        } else {
            // Insert new record with geo data
            $insertData = [
                'ip' => $event->ip,
                'subnet' => $subnet,
                'reputation_score' => 100,
                'total_requests' => 1,
                'failed_requests' => $event->success ? 0 : 1,
                'blocked_count' => 0,
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($geoData) {
                $insertData = array_merge($insertData, $geoData);
            }

            DB::table('ip_reputation')->insert($insertData);
        }

        // Recalculate reputation score
        $this->updateReputationScore($event->ip);
    }

    /**
     * Calculate subnet for IP address
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
     * Update reputation score based on success/failure ratio
     */
    private function updateReputationScore(string $ip): void
    {
        $record = DB::table('ip_reputation')->where('ip', $ip)->first();

        if (! $record) {
            return;
        }

        $total = max($record->total_requests, 1);
        $failed = $record->failed_requests;

        // Calculate success rate
        $successRate = 1 - ($failed / $total);

        // Convert to 0-100 score
        $score = max(0, min(100, floor($successRate * 100)));

        DB::table('ip_reputation')
            ->where('ip', $ip)
            ->update(['reputation_score' => $score]);
    }
}
