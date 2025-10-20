<?php

use App\Actions\WhmcsSynchro;
use Illuminate\Support\Facades\{Schedule};

if (config('unblock.cron_active')) {
    Schedule::command(WhmcsSynchro::class)->dailyAt('02:03');
}
