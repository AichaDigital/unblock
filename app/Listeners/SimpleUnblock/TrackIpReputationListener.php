<?php

declare(strict_types=1);

namespace App\Listeners\SimpleUnblock;

use App\Events\SimpleUnblock\SimpleUnblockRequestProcessed;
use App\Models\IpReputation;
use App\Services\GeoIPService;
use Illuminate\Contracts\Queue\ShouldQueue;

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

        $reputation = IpReputation::query()->firstOrNew(['ip' => $event->ip]);

        if ($reputation->exists) {
            $extra = [
                'subnet' => $subnet,
                'last_seen_at' => now(),
            ];

            // Geo data is backfilled only when not already set
            if ($geoData && ! $reputation->country_code) {
                $extra = array_merge($extra, $geoData);
            }

            $reputation->increment('total_requests', 1, $extra);

            if (! $event->success) {
                $reputation->increment('failed_requests');
            }
        } else {
            $reputation->fill(array_merge([
                'subnet' => $subnet,
                'reputation_score' => 100,
                'total_requests' => 1,
                'failed_requests' => $event->success ? 0 : 1,
                'blocked_count' => 0,
                'last_seen_at' => now(),
            ], $geoData ?? []));

            $reputation->save();
        }

        // Recalculate reputation score
        $this->updateReputationScore($reputation);
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
    private function updateReputationScore(IpReputation $reputation): void
    {
        $reputation->refresh();

        $total = max($reputation->total_requests, 1);
        $failed = $reputation->failed_requests;

        // Calculate success rate
        $successRate = 1 - ($failed / $total);

        // Convert to 0-100 score
        $score = (int) max(0, min(100, floor($successRate * 100)));

        $reputation->update(['reputation_score' => $score]);
    }
}
