<?php

namespace App\Console\Commands;

use App\Models\{Host, Report, User};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class TestEmailJobCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'develop:test-email-job
                            {--admin-only : Send only to admin}
                            {--user-only : Send only to user}';

    /**
     * The console command description.
     */
    protected $description = 'Test email sending via jobs for development purposes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Testing Email Job System...');
        $this->newLine();

        // Show current queue configuration
        $queueConnection = config('queue.default');
        $this->info("📋 Queue Connection: {$queueConnection}");

        // Get first admin user
        $admin = User::where('is_admin', true)->first();
        if (! $admin) {
            $this->error('❌ No admin user found in database');

            return self::FAILURE;
        }

        // Get first host
        $host = Host::first();
        if (! $host) {
            $this->error('❌ No host found in database');

            return self::FAILURE;
        }

        $this->info("👤 Using Admin: {$admin->full_name} ({$admin->email})");
        $this->info("🖥️  Using Host: {$host->fqdn}");

        // Create test report
        $report = $this->createTestReport($admin, $host);
        $this->info("📄 Test Report ID: {$report->id}");
        $this->newLine();

        // Determine email recipients
        $sendToAdmin = ! $this->option('user-only');
        $sendToUser = ! $this->option('admin-only');

        if ($sendToAdmin && $sendToUser) {
            $this->info('📧 Sending emails to: Admin + User');
        } elseif ($sendToAdmin) {
            $this->info('📧 Sending emails to: Admin only');
        } else {
            $this->info('📧 Sending emails to: User only');
        }

        // Job was automatically dispatched by Observer when Report was created
        $this->newLine();
        $this->info('✅ Job dispatched automatically by Observer!');
        $this->newLine();

        // Show next steps based on queue connection
        $this->showNextSteps($queueConnection);

        return self::SUCCESS;
    }

    /**
     * Create a test report - Observer will automatically dispatch job
     */
    private function createTestReport(User $user, Host $host): Report
    {
        $this->info('📄 Creating test report...');

        // Create report normally - Observer will automatically dispatch the job
        return Report::create([
            'user_id' => $user->id,
            'host_id' => $host->id,
            'ip' => '203.0.113.1', // Test IP from RFC 5737
            'was_blocked' => true,
            'analysis_data' => [
                'csf_analysis' => [
                    'blocked' => true,
                    'rules' => ['DENYIN rule found'],
                    'temp_blocks' => ['IP:203.0.113.1 Port: Dir:in TTL:3600'],
                ],
                'logs' => [
                    'exim' => ['Authentication failed for test'],
                    'dovecot' => ['Login failed for test'],
                ],
                'summary' => 'Test firewall analysis - IP was blocked',
            ],
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Show next steps based on queue connection
     */
    private function showNextSteps(string $queueConnection): void
    {
        $this->info('📋 Next Steps:');
        $this->newLine();

        if ($queueConnection === 'sync') {
            $this->line('🔄 Queue is SYNC - Job executed immediately');
            $this->line('📧 Check your email inbox for test messages');
        } elseif ($queueConnection === 'redis') {
            $this->warn('⏳ Queue is REDIS - Job is waiting for worker');
            $this->line('🔧 Run worker to process job:');
            $this->line('   php artisan queue:work redis');
            $this->newLine();
            $this->line('📊 Check job status:');
            $this->line('   php artisan develop:queue-monitor');
        } else {
            $this->line("⏳ Queue is {$queueConnection} - Job dispatched");
            $this->line('🔧 Make sure queue worker is running:');
            $this->line("   php artisan queue:work {$queueConnection}");
        }

        $this->newLine();
        $this->line('🔍 Monitor logs:');
        $this->line('   tail -f storage/logs/laravel.log');
    }
}
