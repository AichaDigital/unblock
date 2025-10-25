<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Queue, Redis};

class QueueMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'develop:queue-monitor
                            {--connection= : Queue connection to monitor (default: current config)}
                            {--watch : Watch mode - refresh every 2 seconds}';

    /**
     * The console command description.
     */
    protected $description = 'Monitor queue status and pending jobs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = $this->option('connection') ?: config('queue.default');

        if ($this->option('watch')) {
            return $this->watchMode($connection);
        }

        return $this->singleCheck($connection);
    }

    /**
     * Single check mode
     */
    private function singleCheck(string $connection): int
    {
        $this->showQueueStatus($connection);

        return self::SUCCESS;
    }

    /**
     * Watch mode - refresh every 2 seconds
     */
    private function watchMode(string $connection): int
    {
        $this->info('🔄 Watching queue status (Press Ctrl+C to stop)...');
        $this->newLine();

        try {
            while (true) {
                // Clear screen
                system('clear');

                $this->line('🕐 '.now()->format('Y-m-d H:i:s'));
                $this->newLine();

                $this->showQueueStatus($connection);

                sleep(2);
            }
        } catch (Exception $e) {
            $this->error('❌ Watch mode interrupted: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Show current queue status
     */
    private function showQueueStatus(string $connection): void
    {
        $this->info("📊 Queue Status - Connection: {$connection}");
        $this->newLine();

        try {
            if ($connection === 'redis') {
                $this->showRedisQueueStatus();
            } elseif ($connection === 'sync') {
                $this->line('🔄 SYNC Queue - Jobs execute immediately');
            } else {
                $this->line("ℹ️  Monitoring for {$connection} connection");
            }

            $this->newLine();
            $this->showWorkerCommands($connection);

        } catch (Exception $e) {
            $this->error('❌ Error checking queue status: '.$e->getMessage());
        }
    }

    /**
     * Show Redis queue specific status
     */
    private function showRedisQueueStatus(): void
    {
        try {
            $redis = Redis::connection();

            // Check different queue types
            $queues = ['default', 'emails', 'reports'];
            $totalJobs = 0;

            $this->line('📋 Queue Status:');

            foreach ($queues as $queue) {
                $queueKey = "queues:{$queue}";
                $length = $redis->llen($queueKey);
                $totalJobs += $length;

                $status = $length > 0 ? "⏳ {$length} pending" : '✅ Empty';
                $this->line("   {$queue}: {$status}");
            }

            $this->newLine();

            // Check failed jobs
            $failedJobs = $redis->llen('queues:failed');
            if ($failedJobs > 0) {
                $this->error("❌ Failed jobs: {$failedJobs}");
            } else {
                $this->line('✅ No failed jobs');
            }

            // Check delayed jobs
            $delayedJobs = $redis->zcard('queues:delayed');
            if ($delayedJobs > 0) {
                $this->line("⏰ Delayed jobs: {$delayedJobs}");
            }

            $this->newLine();

            // Overall status
            if ($totalJobs > 0) {
                $this->warn("⚠️  Total pending jobs: {$totalJobs}");
                $this->line('💡 Jobs are waiting for a worker to process them');
            } else {
                $this->info('✅ All queues are empty');
            }

        } catch (Exception $e) {
            $this->error('❌ Cannot connect to Redis: '.$e->getMessage());
            $this->line('💡 Make sure Redis is running and configured correctly');
        }
    }

    /**
     * Show worker commands
     */
    private function showWorkerCommands(string $connection): void
    {
        $this->line('🔧 Useful Commands:');
        $this->newLine();

        if ($connection === 'redis') {
            $this->line('Start worker:');
            $this->line("   php artisan queue:work {$connection}");
            $this->newLine();

            $this->line('Start worker with options:');
            $this->line("   php artisan queue:work {$connection} --tries=3 --timeout=60");
            $this->newLine();

            $this->line('Process one job only:');
            $this->line("   php artisan queue:work {$connection} --once");
            $this->newLine();

            $this->line('Clear failed jobs:');
            $this->line('   php artisan queue:flush');
            $this->newLine();

            $this->line('Retry failed jobs:');
            $this->line('   php artisan queue:retry all');
        } else {
            $this->line('Start worker:');
            $this->line("   php artisan queue:work {$connection}");
        }

        $this->newLine();
        $this->line('Test email job:');
        $this->line('   php artisan develop:test-email-job');
    }
}
