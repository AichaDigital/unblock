<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Report;
use Illuminate\Support\Facades\Log;

/**
 * Observer for Report model
 *
 * Handles automatic job dispatch for email notifications when reports are created
 */
class ReportObserver
{
    /**
     * Handle the Report "created" event
     *
     * DISABLED: Notifications are handled manually in CheckFirewallAction to pass copyUserId
     */
    public function created(Report $report): void
    {
        // Notifications are handled manually in CheckFirewallAction
        // to properly pass the copyUserId parameter
        Log::debug('Report created, notifications handled manually', [
            'report_id' => $report->id,
            'user_id' => $report->user_id,
            'host_id' => $report->host_id,
            'ip' => $report->ip,
        ]);
    }
}
