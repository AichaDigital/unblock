<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cleanup old OTP records from one_time_passwords table
 *
 * This command removes:
 * - Expired OTP records (past expires_at timestamp)
 * - Old OTP records (older than 7 days)
 *
 * Part of Simple Unblock v1.3.0 - Reputation System
 */
class CleanupTemporaryUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'simple-unblock:cleanup-otp
                            {--days=7 : Delete OTP records older than this many days}
                            {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup old and expired OTP records from Simple Unblock system';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $force = $this->option('force');

        // Count records to be deleted
        $expiredCount = $this->countExpiredRecords();
        $oldCount = $this->countOldRecords($days);
        $totalCount = $expiredCount + $oldCount;

        if ($totalCount === 0) {
            $this->info('✓ No OTP records to cleanup.');

            return self::SUCCESS;
        }

        // Display summary
        $this->newLine();
        $this->line('╔═══════════════════════════════════════════════╗');
        $this->line('║     Simple Unblock OTP Cleanup Summary       ║');
        $this->line('╠═══════════════════════════════════════════════╣');
        $this->line(sprintf('║ Expired OTP records:        %15d ║', $expiredCount));
        $this->line(sprintf('║ Old OTP records (>%d days):  %15d ║', $days, $oldCount));
        $this->line('╠═══════════════════════════════════════════════╣');
        $this->line(sprintf('║ Total records to delete:    %15d ║', $totalCount));
        $this->line('╚═══════════════════════════════════════════════╝');
        $this->newLine();

        // Confirm deletion
        if (! $force && ! $this->confirm('Do you want to proceed with cleanup?', true)) {
            $this->warn('Cleanup cancelled.');

            return self::FAILURE;
        }

        // Delete expired records
        $this->info('Deleting expired OTP records...');
        $deletedExpired = $this->deleteExpiredRecords();
        $this->line("  → Deleted {$deletedExpired} expired records");

        // Delete old records
        $this->info("Deleting OTP records older than {$days} days...");
        $deletedOld = $this->deleteOldRecords($days);
        $this->line("  → Deleted {$deletedOld} old records");

        // Summary
        $totalDeleted = $deletedExpired + $deletedOld;
        $this->newLine();
        $this->info("✓ Cleanup completed! Total records deleted: {$totalDeleted}");

        return self::SUCCESS;
    }

    /**
     * Count expired OTP records
     */
    private function countExpiredRecords(): int
    {
        return DB::table('one_time_passwords')
            ->where('expires_at', '<', now())
            ->count();
    }

    /**
     * Count old OTP records (beyond retention period)
     */
    private function countOldRecords(int $days): int
    {
        return DB::table('one_time_passwords')
            ->where('created_at', '<', now()->subDays($days))
            ->where('expires_at', '>=', now()) // Don't double-count expired records
            ->count();
    }

    /**
     * Delete expired OTP records
     */
    private function deleteExpiredRecords(): int
    {
        return DB::table('one_time_passwords')
            ->where('expires_at', '<', now())
            ->delete();
    }

    /**
     * Delete old OTP records (beyond retention period)
     */
    private function deleteOldRecords(int $days): int
    {
        return DB::table('one_time_passwords')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
