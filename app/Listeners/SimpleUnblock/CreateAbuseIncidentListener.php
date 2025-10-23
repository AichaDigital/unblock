<?php

declare(strict_types=1);

namespace App\Listeners\SimpleUnblock;

use App\Events\SimpleUnblock\{SimpleUnblockHoneypotTriggered, SimpleUnblockIpMismatch, SimpleUnblockOtpFailed, SimpleUnblockRateLimitExceeded};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

/**
 * Creates abuse incident records based on security events
 *
 * Handles:
 * - Rate limit exceeded
 * - Honeypot triggered
 * - OTP failures
 * - IP mismatches
 */
class CreateAbuseIncidentListener implements ShouldQueue
{
    public function handle(
        SimpleUnblockRateLimitExceeded|SimpleUnblockHoneypotTriggered|SimpleUnblockOtpFailed|SimpleUnblockIpMismatch $event
    ): void {
        match (true) {
            $event instanceof SimpleUnblockRateLimitExceeded => $this->handleRateLimitExceeded($event),
            $event instanceof SimpleUnblockHoneypotTriggered => $this->handleHoneypotTriggered($event),
            $event instanceof SimpleUnblockOtpFailed => $this->handleOtpFailed($event),
            $event instanceof SimpleUnblockIpMismatch => $this->handleIpMismatch($event),
        };
    }

    private function handleRateLimitExceeded(SimpleUnblockRateLimitExceeded $event): void
    {
        // Determine severity based on vector
        $severity = match ($event->vector) {
            'subnet', 'global' => 'high',
            'domain', 'email' => 'medium',
            default => 'low',
        };

        DB::table('abuse_incidents')->insert([
            'incident_type' => 'rate_limit_exceeded',
            'ip_address' => $this->extractIpFromIdentifier($event->identifier, $event->vector),
            'email_hash' => $event->vector === 'email' ? $event->identifier : null,
            'domain' => $event->vector === 'domain' ? $event->identifier : null,
            'severity' => $severity,
            'description' => "Rate limit exceeded for {$event->vector}: {$event->attempts}/{$event->maxAttempts} attempts",
            'metadata' => json_encode([
                'vector' => $event->vector,
                'attempts' => $event->attempts,
                'max_attempts' => $event->maxAttempts,
                'identifier' => $event->identifier,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Decrease reputation score
        $this->decreaseReputation($event->vector, $event->identifier, 10);
    }

    private function handleHoneypotTriggered(SimpleUnblockHoneypotTriggered $event): void
    {
        DB::table('abuse_incidents')->insert([
            'incident_type' => 'honeypot_triggered',
            'ip_address' => $event->ip,
            'email_hash' => $event->email ? hash('sha256', $event->email) : null,
            'domain' => $event->domain,
            'severity' => 'medium',
            'description' => 'Honeypot triggered - likely bot activity',
            'metadata' => json_encode([
                'ip' => $event->ip,
                'email' => $event->email ? 'REDACTED' : null,
                'domain' => $event->domain,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Decrease IP reputation
        $this->decreaseReputation('ip', $event->ip, 20);
    }

    private function handleOtpFailed(SimpleUnblockOtpFailed $event): void
    {
        $emailHash = hash('sha256', $event->email);

        // Check for multiple recent failures (brute force detection)
        $recentFailures = DB::table('abuse_incidents')
            ->where('incident_type', 'invalid_otp_attempts')
            ->where('email_hash', $emailHash)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        $severity = $recentFailures >= 3 ? 'high' : 'medium';

        DB::table('abuse_incidents')->insert([
            'incident_type' => 'invalid_otp_attempts',
            'ip_address' => $event->ip,
            'email_hash' => $emailHash,
            'severity' => $severity,
            'description' => "OTP verification failed: {$event->reason}",
            'metadata' => json_encode([
                'ip' => $event->ip,
                'reason' => $event->reason,
                'recent_failures' => $recentFailures + 1,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Decrease reputation based on severity
        $penalty = $severity === 'high' ? 30 : 15;
        $this->decreaseReputation('email', $emailHash, $penalty);
        $this->decreaseReputation('ip', $event->ip, $penalty / 2);
    }

    private function handleIpMismatch(SimpleUnblockIpMismatch $event): void
    {
        $emailHash = hash('sha256', $event->email);

        DB::table('abuse_incidents')->insert([
            'incident_type' => 'ip_mismatch',
            'ip_address' => $event->verificationIp,
            'email_hash' => $emailHash,
            'severity' => 'critical',
            'description' => 'IP mismatch detected during OTP verification - possible relay attack',
            'metadata' => json_encode([
                'original_ip' => $event->originalIp,
                'verification_ip' => $event->verificationIp,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Heavy penalty for potential attack
        $this->decreaseReputation('ip', $event->verificationIp, 40);
        $this->decreaseReputation('email', $emailHash, 40);
    }

    /**
     * Decrease reputation score
     */
    private function decreaseReputation(string $type, string $identifier, int $penalty): void
    {
        $table = $type === 'ip' ? 'ip_reputation' : 'email_reputation';
        $column = $type === 'ip' ? 'ip' : 'email_hash';

        DB::table($table)
            ->where($column, $identifier)
            ->update([
                'reputation_score' => DB::raw("GREATEST(0, reputation_score - {$penalty})"),
                'updated_at' => now(),
            ]);
    }

    /**
     * Extract IP from identifier based on vector type
     */
    private function extractIpFromIdentifier(string $identifier, string $vector): string
    {
        if ($vector === 'ip' || $vector === 'subnet') {
            return explode('/', $identifier)[0] ?? $identifier;
        }

        return 'unknown';
    }
}
