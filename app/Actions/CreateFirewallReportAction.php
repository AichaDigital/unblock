<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Report;
use Lorisleiva\Actions\Concerns\{AsAction, AsJob};
use Throwable;

/**
 * Action to create firewall check reports
 *
 * This action handles:
 * - Report creation
 * - Log storage
 * - Analysis storage
 */
class CreateFirewallReportAction
{
    use AsAction, AsJob;

    /**
     * Handle report creation process
     *
     * @param  int  $userId  User who requested the check
     * @param  int  $hostId  Host that was checked
     * @param  string  $ip  IP that was checked
     * @param  array<string, mixed>  $checkResults  Results from server check
     * @param  array<string, mixed>  $analysisResults  Results from log analysis
     * @return array{
     *     success: bool,
     *     report_id?: int,
     *     error?: string
     * }
     */
    public function handle(
        int $userId,
        int $hostId,
        string $ip,
        array $checkResults,
        array $analysisResults
    ): array {
        try {
            $report = Report::create([
                'user_id' => $userId,
                'host_id' => $hostId,
                'ip' => $ip,
                'logs' => $checkResults['logs'] ?? [],
                'analysis' => $analysisResults['analysis'] ?? [],
            ]);

            return [
                'success' => true,
                'report_id' => $report->id,
            ];

        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
