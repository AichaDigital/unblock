<?php

declare(strict_types=1);

namespace App\Listeners\SimpleUnblock;

use App\Events\SimpleUnblock\{SimpleUnblockOtpFailed, SimpleUnblockOtpSent, SimpleUnblockOtpVerified};
use App\Models\EmailReputation;
use Illuminate\Contracts\Queue\ShouldQueue;

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

        $reputation = EmailReputation::query()->firstOrNew(['email_hash' => $emailHash]);

        if ($reputation->exists) {
            $reputation->increment('total_requests', 1, [
                'email_domain' => $emailDomain,
                'last_seen_at' => now(),
            ]);

            if ($event instanceof SimpleUnblockOtpVerified) {
                $reputation->increment('verified_requests');
            }

            if ($event instanceof SimpleUnblockOtpFailed) {
                $reputation->increment('failed_requests');
            }
        } else {
            $reputation->fill([
                'email_domain' => $emailDomain,
                'reputation_score' => 100,
                'total_requests' => 1,
                'failed_requests' => ($event instanceof SimpleUnblockOtpFailed) ? 1 : 0,
                'verified_requests' => ($event instanceof SimpleUnblockOtpVerified) ? 1 : 0,
                'last_seen_at' => now(),
            ]);

            $reputation->save();
        }

        // Recalculate reputation score
        $this->updateReputationScore($reputation);
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
    private function updateReputationScore(EmailReputation $reputation): void
    {
        $reputation->refresh();

        $total = max($reputation->total_requests, 1);
        $failed = $reputation->failed_requests;

        // Calculate success rate
        $successRate = 1 - ($failed / $total);

        // Bonus for verified requests
        $verifiedRatio = $reputation->verified_requests / $total;
        $bonusScore = (int) floor($verifiedRatio * 20); // Up to +20 points

        // Convert to 0-100 score with bonus
        $baseScore = (int) max(0, min(100, floor($successRate * 100)));
        $score = min(100, $baseScore + $bonusScore);

        $reputation->update(['reputation_score' => $score]);
    }
}
