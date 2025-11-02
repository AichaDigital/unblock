<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Sync\{SyncCpanelAccountsAction, SyncDirectAdminAccountsAction};
use App\Enums\PanelType;
use App\Models\Host;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use function Laravel\Prompts\progress;

/**
 * Sync Accounts Command
 *
 * Synchronizes hosting accounts and domains from remote servers (cPanel/DirectAdmin)
 * to the local database for fast validation in Simple Mode.
 *
 * Usage:
 * - php artisan sync:accounts                    # Sync all hosts
 * - php artisan sync:accounts --initial          # Initial sync (no marking deleted)
 * - php artisan sync:accounts --host=1           # Sync specific host
 * - php artisan sync:accounts --initial --host=1 # Initial sync for specific host
 */
class SyncAccountsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sync:accounts
                            {--initial : Initial sync mode (does not mark deleted accounts)}
                            {--host= : Sync only a specific host ID}';

    /**
     * The console command description.
     */
    protected $description = 'Synchronize hosting accounts and domains from remote servers to local database';

    /**
     * Execute the console command.
     */
    public function handle(
        SyncCpanelAccountsAction $cpanelAction,
        SyncDirectAdminAccountsAction $directAdminAction
    ): int {
        $this->info('🔄 Starting Accounts Synchronization');
        $this->newLine();

        $isInitial = $this->option('initial');
        $hostId = $this->option('host');

        if ($isInitial) {
            $this->warn('⚠️  Running in INITIAL mode - Will not mark deleted accounts');
            $this->newLine();
        }

        try {
            // Get hosts to sync
            $hosts = $this->getHostsToSync($hostId);

            if ($hosts->isEmpty()) {
                $this->warn('No hosts found to sync');

                return self::FAILURE;
            }

            $this->info("Found {$hosts->count()} host(s) to synchronize");
            $this->newLine();

            // Initialize totals
            $totals = [
                'created' => 0,
                'updated' => 0,
                'suspended' => 0,
                'deleted' => 0,
            ];

            // Process each host with progress bar
            $results = progress(
                label: 'Synchronizing hosts',
                steps: $hosts,
                callback: function (Host $host) use ($cpanelAction, $directAdminAction, $isInitial, &$totals) {
                    try {
                        $stats = $this->syncHost($host, $cpanelAction, $directAdminAction, $isInitial);

                        $totals['created'] += $stats['created'];
                        $totals['updated'] += $stats['updated'];
                        $totals['suspended'] += $stats['suspended'];
                        $totals['deleted'] += $stats['deleted'];

                        return $stats;

                    } catch (Exception $e) {
                        $this->error("Failed to sync {$host->fqdn}: {$e->getMessage()}");

                        return null;
                    }
                }
            );

            $this->newLine();

            // Display summary
            $this->displaySummary($totals, $hosts->count());

            // Log completion
            Log::info('Accounts sync completed', $totals);

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error('Sync failed: '.$e->getMessage());
            Log::error('Accounts sync command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Get hosts to synchronize based on options
     */
    private function getHostsToSync(?string $hostId): \Illuminate\Database\Eloquent\Collection
    {
        $query = Host::query()
            ->whereIn('panel', ['cpanel', 'directadmin', 'da'])
            ->whereNull('deleted_at');

        if ($hostId) {
            $query->where('id', $hostId);
        }

        return $query->get();
    }

    /**
     * Sync a single host
     */
    private function syncHost(
        Host $host,
        SyncCpanelAccountsAction $cpanelAction,
        SyncDirectAdminAccountsAction $directAdminAction,
        bool $isInitial
    ): array {
        $panelType = $host->panel;

        // Select appropriate action based on panel type
        // Note: $panelType is an Enum, so we compare with Enum cases or use ->value
        $action = match ($panelType) {
            PanelType::CPANEL => $cpanelAction,
            PanelType::DIRECTADMIN => $directAdminAction,
            default => throw new Exception("Unsupported panel type: {$panelType->value}")
        };

        // Execute sync
        $stats = $action->handle($host, $isInitial);

        return $stats;
    }

    /**
     * Display summary of synchronization
     */
    private function displaySummary(array $totals, int $hostsCount): void
    {
        $this->info('✅ Synchronization Complete');
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Hosts Synced', $hostsCount],
                ['Accounts Created', $totals['created']],
                ['Accounts Updated', $totals['updated']],
                ['Accounts Suspended', $totals['suspended']],
                ['Accounts Deleted', $totals['deleted']],
            ]
        );

        $this->newLine();
    }
}
