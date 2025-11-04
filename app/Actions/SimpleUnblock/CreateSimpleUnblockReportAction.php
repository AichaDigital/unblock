<?php

declare(strict_types=1);

namespace App\Actions\SimpleUnblock;

use App\Models\{Host, Report};
use App\Services\{AnonymousUserService, CsfOutputParser};
use App\Services\Firewall\FirewallAnalysisResult;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Create Simple Unblock Report Action
 *
 * Creates a database report for Simple Unblock requests.
 * Report structure includes analysis results, unblock operations,
 * and decision information for audit trail.
 */
class CreateSimpleUnblockReportAction
{
    use AsAction;

    /**
     * Create report for simple unblock operation
     */
    public function handle(
        string $ip,
        string $domain,
        string $email,
        Host $host,
        FirewallAnalysisResult $analysis,
        ?array $unblockResults,
        UnblockDecision $decision
    ): Report {
        Log::info('Creating simple unblock report', [
            'ip' => $ip,
            'domain' => $domain,
            'host_id' => $host->id,
            'decision' => $decision->reason,
        ]);

        // Parse CSF output to extract human-readable information
        $csfSummary = $this->parseCsfOutput($analysis->getLogs());

        $report = Report::create([
            'ip' => $ip,
            'user_id' => AnonymousUserService::get()->id,
            'host_id' => $host->id,
            'was_unblocked' => $decision->shouldUnblock, // CRITICAL: Direct column for email notifications
            'analysis' => [
                'was_blocked' => $analysis->isBlocked(),
                'domain' => $domain,
                'email' => $email,
                'simple_mode' => true,
                'unblock_performed' => $decision->shouldUnblock,
                'unblock_status' => $unblockResults,
                'decision_reason' => $decision->reason,
                'analysis_timestamp' => now()->toISOString(),
                'block_summary' => $csfSummary, // Human-readable block info
            ],
            'logs' => $analysis->getLogs(),
        ]);

        Log::info('Simple unblock report created', [
            'report_id' => $report->id,
            'ip' => $ip,
            'domain' => $domain,
        ]);

        return $report;
    }

    /**
     * Parse CSF output from logs to extract human-readable summary
     */
    private function parseCsfOutput(array $logs): ?array
    {
        // Look for CSF output in logs
        $csfOutput = $logs['csf'] ?? $logs['firewall'] ?? null;

        if (! $csfOutput || empty(trim($csfOutput))) {
            return null;
        }

        $parser = new CsfOutputParser;

        return $parser->extractHumanReadableSummary($csfOutput);
    }
}
