<?php

declare(strict_types=1);

namespace App\Actions\SimpleUnblock;

use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Evaluate Unblock Match Action
 *
 * CRITICAL BUSINESS LOGIC - Determines if an IP should be unblocked.
 *
 * Decision Table:
 * ┌────────────┬──────────────┬─────────────┬──────────────┬────────────┬─────────────┐
 * │ IP Blocked │ Domain Logs  │ Domain in DB│ Decision     │ Notify User│ Notify Admin│
 * ├────────────┼──────────────┼─────────────┼──────────────┼────────────┼─────────────┤
 * │ true       │ true         │ true        │ UNBLOCK      │ YES        │ YES         │
 * │ true       │ false        │ true        │ UNBLOCK*     │ YES        │ YES         │
 * │ true       │ true         │ false       │ ABORT        │ NO         │ YES (alert) │
 * │ true       │ false        │ false       │ ABORT        │ NO         │ YES (alert) │
 * │ false      │ true         │ true        │ NO UNBLOCK   │ YES**      │ YES (info)  │
 * │ false      │ false        │ true        │ NO MATCH     │ YES**      │ YES (info)  │
 * │ false      │ *            │ false       │ ABORT        │ NO         │ YES (alert) │
 * └────────────┴──────────────┴─────────────┴──────────────┴────────────┴─────────────┘
 *
 * * IMPORTANT: If domain is validated in DB and IP is blocked, we UNBLOCK
 *   even if not found in recent logs (logs may be rotated/cleaned).
 *
 * ** FIXED: User IS notified when domain is valid but IP not blocked
 *   (They deserve feedback about their request result)
 */
class EvaluateUnblockMatchAction
{
    use AsAction;

    /**
     * Evaluate if IP should be unblocked based on analysis results
     *
     * @param  bool  $ipIsBlocked  Whether IP is currently blocked in firewall
     * @param  bool  $domainFoundInLogs  Whether domain was found in server logs with this IP
     * @param  bool  $domainValidInDb  Whether domain exists and is active in database
     */
    public function handle(
        bool $ipIsBlocked,
        bool $domainFoundInLogs,
        bool $domainValidInDb
    ): UnblockDecision {
        Log::info('Evaluating unblock decision', [
            'ip_blocked' => $ipIsBlocked,
            'domain_in_logs' => $domainFoundInLogs,
            'domain_valid_in_db' => $domainValidInDb,
        ]);

        // ABORT scenarios: Domain not valid in database
        if (! $domainValidInDb) {
            Log::warning('Unblock evaluation: Domain not valid in DB - ABORT', [
                'ip_blocked' => $ipIsBlocked,
                'domain_in_logs' => $domainFoundInLogs,
            ]);

            return UnblockDecision::abort('domain_not_valid_in_database');
        }

        // UNBLOCK scenarios: IP blocked + Domain valid in DB
        if ($ipIsBlocked) {
            if ($domainFoundInLogs) {
                Log::info('Unblock evaluation: FULL MATCH - IP blocked + Domain in logs + Domain valid in DB');

                return UnblockDecision::unblock('full_match');
            }

            // CRITICAL: Unblock even if not found in logs (logs may be old/rotated)
            Log::info('Unblock evaluation: IP blocked + Domain valid in DB (not in recent logs) - UNBLOCK');

            return UnblockDecision::unblock('domain_validated_in_db');
        }

        // NO UNBLOCK scenarios: IP not blocked
        if ($domainFoundInLogs) {
            Log::info('Unblock evaluation: Domain found but IP not blocked - NO ACTION');

            return UnblockDecision::noMatch('domain_found_but_ip_not_blocked');
        }

        // Default: No match found
        Log::info('Unblock evaluation: No conditions met - NO ACTION');

        return UnblockDecision::noMatch('no_match_found');
    }
}
