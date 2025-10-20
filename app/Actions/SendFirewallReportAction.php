<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\LogNotificationMail;
use App\Models\User;
use Illuminate\Support\Facades\{Log, Mail};
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\{AsAction, AsJob};
use Throwable;

/**
 * Action to send firewall check reports
 *
 * This action handles:
 * - Error notification to admins
 * - Log notification emails
 */
class SendFirewallReportAction
{
    use AsAction, AsJob;

    /**
     * Handle report sending process
     *
     * @param  Throwable  $error  Error that occurred
     * @param  int  $userId  User who requested the check
     * @param  int|null  $hostId  Host that was checked
     * @param  string  $ip  IP that was checked
     * @return array{
     *     success: bool,
     *     error?: string,
     *     report_id?: int
     * }
     */
    public function handle(
        Throwable $error,
        int $userId,
        ?int $hostId,
        string $ip
    ): array {
        try {
            // 1. ALWAYS create a report for error tracking
            $reportResult = CreateFirewallReportAction::run(
                userId: $userId,
                hostId: $hostId ?? 0, // Use 0 if no host ID available
                ip: $ip,
                checkResults: [
                    'logs' => [],
                    'error' => $error->getMessage(),
                    'key_name' => 'error',
                ],
                analysisResults: [
                    'was_blocked' => false,
                    'analysis' => [
                        'error' => $error->getMessage(),
                        'error_type' => get_class($error),
                        'created_at' => now()->toISOString(),
                    ],
                ]
            );

            $reportId = $reportResult['report_id'] ?? null;

            // 2. Get admin user for notification
            $adminUser = User::where('is_admin', true)->first();
            if (! $adminUser) {
                Log::error('No admin user found for error notification');

                return [
                    'success' => false,
                    'error' => 'No admin user found',
                    'report_id' => $reportId,
                ];
            }

            // 3. Notify admins about errors
            $hostInfo = 'Unknown Host';
            if ($hostId) {
                $host = \App\Models\Host::find($hostId);
                $hostInfo = $host ? $host->fqdn : "Host ID: {$hostId}";
            }

            if (config('unblock.send_admin_report_email')) {
                Mail::to(config('unblock.admin_email'))->send(
                    new LogNotificationMail(
                        user: $adminUser,
                        report: [
                            'error' => $error->getMessage(),
                            'host' => $hostInfo,
                        ],
                        ip: $ip,
                        is_unblock: false,
                        report_uuid: $reportId ? (string) $reportId : (string) Str::uuid()
                    )
                );
            }

            return [
                'success' => true,
                'report_id' => $reportId,
            ];

        } catch (Throwable $e) {
            Log::error('Failed to send firewall report', [
                'error' => $e->getMessage(),
                'original_error' => $error->getMessage(),
                'user_id' => $userId,
                'host_id' => $hostId,
                'ip' => $ip,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
