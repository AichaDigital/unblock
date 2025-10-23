<?php

declare(strict_types=1);

namespace App\Listeners\SimpleUnblock;

use App\Events\SimpleUnblock\SimpleUnblockRequestProcessed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

/**
 * Tracks IP reputation based on Simple Unblock requests
 *
 * Updates ip_reputation table with request statistics
 * Calculates reputation score based on success/failure ratio
 */
class TrackIpReputationListener implements ShouldQueue
{
    public function handle(SimpleUnblockRequestProcessed $event): void
    {
        $subnet = $this->calculateSubnet($event->ip);

        // Update or create IP reputation
        $reputation = DB::table('ip_reputation')->updateOrInsert(
            ['ip' => $event->ip],
            [
                'subnet' => $subnet,
                'total_requests' => DB::raw('total_requests + 1'),
                'failed_requests' => DB::raw($event->success ? 'failed_requests' : 'failed_requests + 1'),
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]
        );

        // If new record, set defaults
        if ($reputation) {
            DB::table('ip_reputation')
                ->where('ip', $event->ip)
                ->whereNull('created_at')
                ->update([
                    'reputation_score' => 100,
                    'created_at' => now(),
                ]);
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
