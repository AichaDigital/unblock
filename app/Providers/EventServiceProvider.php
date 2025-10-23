<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\SimpleUnblock\{SimpleUnblockHoneypotTriggered, SimpleUnblockIpMismatch, SimpleUnblockOtpFailed, SimpleUnblockOtpSent, SimpleUnblockOtpVerified, SimpleUnblockRateLimitExceeded, SimpleUnblockRequestProcessed};
use App\Listeners\SimpleUnblock\{CreateAbuseIncidentListener, TrackEmailReputationListener, TrackIpReputationListener};
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Simple Unblock - Reputation Tracking (v1.3.0)
        SimpleUnblockRequestProcessed::class => [
            TrackIpReputationListener::class,
        ],

        SimpleUnblockOtpSent::class => [
            TrackEmailReputationListener::class,
        ],

        SimpleUnblockOtpVerified::class => [
            TrackEmailReputationListener::class,
        ],

        SimpleUnblockOtpFailed::class => [
            TrackEmailReputationListener::class,
            CreateAbuseIncidentListener::class,
        ],

        SimpleUnblockRateLimitExceeded::class => [
            CreateAbuseIncidentListener::class,
        ],

        SimpleUnblockHoneypotTriggered::class => [
            CreateAbuseIncidentListener::class,
        ],

        SimpleUnblockIpMismatch::class => [
            CreateAbuseIncidentListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
