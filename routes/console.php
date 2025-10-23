<?php

use App\Actions\WhmcsSynchro;
use App\Jobs\RemoveExpiredBfmWhitelistIps;
use Illuminate\Support\Facades\{Schedule};

if (config('unblock.cron_active')) {
    Schedule::command(WhmcsSynchro::class)->dailyAt('02:03');
}

// Simple Unblock: Cleanup old OTP records (v1.3.0)
Schedule::command('simple-unblock:cleanup-otp --force')->dailyAt('03:00');

// DirectAdmin BFM: Remove expired whitelist IPs (runs every hour)
Schedule::job(new RemoveExpiredBfmWhitelistIps)->hourly();
