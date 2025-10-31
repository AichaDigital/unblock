<?php

use App\Actions\WhmcsSynchro;
use App\Jobs\RemoveExpiredBfmWhitelistIps;
use Illuminate\Support\Facades\{File, Schedule};

if (config('unblock.cron_active')) {
    Schedule::command(WhmcsSynchro::class)->dailyAt('02:03');
}

// Simple Unblock: Cleanup old OTP records (v1.3.0)
Schedule::command('simple-unblock:cleanup-otp --force')->dailyAt('03:00');

// DirectAdmin BFM: Remove expired whitelist IPs (runs every hour)
Schedule::job(new RemoveExpiredBfmWhitelistIps)->hourly();

// Pattern Detection: Detect attack patterns (v1.4.0 - runs every hour)
Schedule::command('patterns:detect --force')->hourly();

// GeoIP: Update MaxMind database (v1.4.0 - runs weekly on Sundays at 2am)
Schedule::command('geoip:update')->weekly()->sundays()->at('02:00');

// Accounts Sync: Synchronize hosting accounts from remote servers (Phase 2 - Simple Mode Refactor)
if (config('unblock.sync.schedule_enabled')) {
    Schedule::command('sync:accounts')
        ->cron(config('unblock.sync.schedule_cron'))
        ->withoutOverlapping()
        ->runInBackground();
}

// SSH Keys Cleanup: Remove temporary SSH keys older than 1 day
Schedule::call(function () {
    $sshPath = storage_path('app/.ssh');

    if (! File::isDirectory($sshPath)) {
        return;
    }

    $oneDayAgo = now()->subDay()->timestamp;
    $files = File::files($sshPath);
    $deletedCount = 0;

    foreach ($files as $file) {
        $filePath = $file->getPathname();
        if (File::lastModified($filePath) < $oneDayAgo) {
            File::delete($filePath);
            $deletedCount++;
        }
    }

    if ($deletedCount > 0) {
        logger()->info("SSH keys cleanup: deleted {$deletedCount} old temporary keys");
    }
})->dailyAt('07:00')->name('cleanup-ssh-keys');
