<?php

declare(strict_types=1);

namespace App\Listeners\SimpleUnblock;

use App\Events\SimpleUnblock\{SimpleUnblockOtpFailed, SimpleUnblockOtpSent, SimpleUnblockOtpVerified};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

/**
 * Tracks email reputation based on OTP actions
 *
 * Updates email_reputation table with OTP statistics
 * Calculates reputation score based on verification success
 * GDPR compliant: stores SHA-256 hash, not plaintext email
 */
class TrackEmailReputationListener implements ShouldQueue
{
    public function handle(SimpleUnblockOtpSent|SimpleUnblockOtpVerified|SimpleUnblockOtpFailed $event): void
    {
        $emailHash = hash('sha256', $event->email);
        $emailDomain = $this->extractDomain($event->email);

        // Check if record exists
        $exists = DB::table('email_reputation')->where('email_hash', $emailHash)->exists();

        if ($exists) {
            // Update existing record
            $updates = [
                'email_domain' => $emailDomain,
                'total_requests' => DB::raw('total_requests + 1'),
                'last_seen_at' => now(),
                'updated_at' => now(),
            ];

            if ($event instanceof SimpleUnblockOtpVerified) {
                $updates['verified_requests'] = DB::raw('verified_requests + 1');
            }

            if ($event instanceof SimpleUnblockOtpFailed) {
                $updates['failed_requests'] = DB::raw('failed_requests + 1');
            }

            DB::table('email_reputation')
                ->where('email_hash', $emailHash)
                ->update($updates);
        } else {
            // Insert new record
            DB::table('email_reputation')->insert([
                'email_hash' => $emailHash,
                'email_domain' => $emailDomain,
                'reputation_score' => 100,
                'total_requests' => 1,
                'failed_requests' => ($event instanceof SimpleUnblockOtpFailed) ? 1 : 0,
                'verified_requests' => ($event instanceof SimpleUnblockOtpVerified) ? 1 : 0,
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Recalculate reputation score
        $this->updateReputationScore($emailHash);
    }

    /**
     * Extract domain from email address
     */
    private function extractDomain(string $email): string
    {
        $parts = explode('@', $email);

        return $parts[1] ?? 'unknown';
    }

    /**
     * Update reputation score based on verification success rate
     */
    private function updateReputationScore(string $emailHash): void
    {
        $record = DB::table('email_reputation')->where('email_hash', $emailHash)->first();

        if (! $record) {
            return;
        }

        $total = max($record->total_requests, 1);
        $failed = $record->failed_requests;

        // Calculate success rate
        $successRate = 1 - ($failed / $total);

        // Bonus for verified requests
        $verifiedRatio = $record->verified_requests / $total;
        $bonusScore = floor($verifiedRatio * 20); // Up to +20 points

        // Convert to 0-100 score with bonus
        $baseScore = max(0, min(100, floor($successRate * 100)));
        $score = min(100, $baseScore + $bonusScore);

        DB::table('email_reputation')
            ->where('email_hash', $emailHash)
            ->update(['reputation_score' => $score]);
    }
}
