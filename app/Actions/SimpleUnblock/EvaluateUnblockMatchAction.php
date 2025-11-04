<?php

declare(strict_types=1);

namespace App\Actions\SimpleUnblock;

use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Evaluate Unblock Match Action
 *
 * CRITICAL BUSINESS LOGIC - Determines the unblock decision based on server analysis results.
 * This action is called AFTER initial validation (e.g., domain ownership) is confirmed.
 *
 * Decision Table (New Logic):
 * ┌────────────┬───────────────┬───────────────────┬──────────────────────────────────┐
 * │ IP Blocked │ Domain Logs   │ Decision          │ Action                           │
 * │ (in CSF)   │ (for this IP) │                   │                                  │
 * ├────────────┼───────────────┼───────────────────┼──────────────────────────────────┤
 * │ true       │ *             │ UNBLOCK           │ Unblock IP + Whitelist IP (TTL)  │
 * │ false      │ true          │ GATHER_DATA       │ Report logs, no unblock action   │
 * │ false      │ false         │ NO_MATCH          │ Report no findings, no action    │
 * └────────────┴───────────────┴───────────────────┴──────────────────────────────────┘
 *
 * - `$domainValidInDb` is removed as it's a pre-condition, not part of this decision.
 * - Whitelisting is now an implicit part of the UNBLOCK decision.
 * - All investigation paths lead to a report and user/admin notification downstream.
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
        // The `$domainValidInDb` parameter is no longer needed here,
        // as this validation should happen before this action is ever called.
        // We leave it for now to avoid breaking the call signature and will remove it in a later refactor.
        bool $domainValidInDb = true
    ): UnblockDecision {
        Log::info('Evaluating unblock decision', [
            'ip_blocked' => $ipIsBlocked,
            'domain_in_logs' => $domainFoundInLogs,
            'domain_valid_in_db (deprecated)' => $domainValidInDb,
        ]);

        // If the IP is blocked in the firewall (CSF), the decision is always to unblock.
        // The logic for whitelisting will be handled by the service consuming this decision.
        if ($ipIsBlocked) {
            Log::info('Unblock evaluation: IP is blocked in CSF. Decision: UNBLOCK');

            return UnblockDecision::unblock('ip_blocked_in_csf');
        }

        // If the IP is not blocked, we check if there's any activity in the logs.
        if ($domainFoundInLogs) {
            Log::info('Unblock evaluation: IP not blocked, but logs found. Decision: GATHER_DATA');

            return UnblockDecision::gatherData('logs_found_no_block');
        }

        // If the IP is not blocked and no relevant logs are found, there's nothing to report.
        Log::info('Unblock evaluation: IP not blocked and no logs found. Decision: NO_MATCH');

        return UnblockDecision::noMatch('ip_not_blocked_no_logs');
    }
}
