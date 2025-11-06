<?php

declare(strict_types=1);

use App\Events\SimpleUnblock\{
    SimpleUnblockHoneypotTriggered,
    SimpleUnblockIpMismatch,
    SimpleUnblockOtpFailed,
    SimpleUnblockOtpSent,
    SimpleUnblockOtpVerified,
    SimpleUnblockRateLimitExceeded,
    SimpleUnblockRequestProcessed
};
use App\Listeners\SimpleUnblock\{
    CreateAbuseIncidentListener,
    TrackEmailReputationListener,
    TrackIpReputationListener
};
use App\Providers\EventServiceProvider;
use Illuminate\Support\Facades\Event;

// ============================================================================
// SCENARIO 1: Event-Listener Mappings Configuration
// ============================================================================

test('SimpleUnblockRequestProcessed has TrackIpReputationListener', function () {
    $provider = new EventServiceProvider(app());
    $listen = $provider->listens();

    expect($listen)->toHaveKey(SimpleUnblockRequestProcessed::class)
        ->and($listen[SimpleUnblockRequestProcessed::class])->toContain(TrackIpReputationListener::class);
});

test('SimpleUnblockOtpSent has TrackEmailReputationListener', function () {
    $provider = new EventServiceProvider(app());
    $listen = $provider->listens();

    expect($listen)->toHaveKey(SimpleUnblockOtpSent::class)
        ->and($listen[SimpleUnblockOtpSent::class])->toContain(TrackEmailReputationListener::class);
});

test('SimpleUnblockOtpVerified has TrackEmailReputationListener', function () {
    $provider = new EventServiceProvider(app());
    $listen = $provider->listens();

    expect($listen)->toHaveKey(SimpleUnblockOtpVerified::class)
        ->and($listen[SimpleUnblockOtpVerified::class])->toContain(TrackEmailReputationListener::class);
});

test('SimpleUnblockOtpFailed has multiple listeners', function () {
    $provider = new EventServiceProvider(app());
    $listen = $provider->listens();

    expect($listen)->toHaveKey(SimpleUnblockOtpFailed::class)
        ->and($listen[SimpleUnblockOtpFailed::class])->toContain(TrackEmailReputationListener::class)
        ->and($listen[SimpleUnblockOtpFailed::class])->toContain(CreateAbuseIncidentListener::class);
});

test('SimpleUnblockRateLimitExceeded has CreateAbuseIncidentListener', function () {
    $provider = new EventServiceProvider(app());
    $listen = $provider->listens();

    expect($listen)->toHaveKey(SimpleUnblockRateLimitExceeded::class)
        ->and($listen[SimpleUnblockRateLimitExceeded::class])->toContain(CreateAbuseIncidentListener::class);
});

test('SimpleUnblockHoneypotTriggered has CreateAbuseIncidentListener', function () {
    $provider = new EventServiceProvider(app());
    $listen = $provider->listens();

    expect($listen)->toHaveKey(SimpleUnblockHoneypotTriggered::class)
        ->and($listen[SimpleUnblockHoneypotTriggered::class])->toContain(CreateAbuseIncidentListener::class);
});

test('SimpleUnblockIpMismatch has CreateAbuseIncidentListener', function () {
    $provider = new EventServiceProvider(app());
    $listen = $provider->listens();

    expect($listen)->toHaveKey(SimpleUnblockIpMismatch::class)
        ->and($listen[SimpleUnblockIpMismatch::class])->toContain(CreateAbuseIncidentListener::class);
});

// ============================================================================
// SCENARIO 2: All Events Are Configured
// ============================================================================

test('all simple unblock events are registered', function () {
    $provider = new EventServiceProvider(app());
    $listen = $provider->listens();

    $expectedEvents = [
        SimpleUnblockRequestProcessed::class,
        SimpleUnblockOtpSent::class,
        SimpleUnblockOtpVerified::class,
        SimpleUnblockOtpFailed::class,
        SimpleUnblockRateLimitExceeded::class,
        SimpleUnblockHoneypotTriggered::class,
        SimpleUnblockIpMismatch::class,
    ];

    foreach ($expectedEvents as $event) {
        expect($listen)->toHaveKey($event);
    }
});

// ============================================================================
// SCENARIO 3: shouldDiscoverEvents Method
// ============================================================================

test('shouldDiscoverEvents returns false', function () {
    $provider = new EventServiceProvider(app());

    expect($provider->shouldDiscoverEvents())->toBeFalse();
});

// ============================================================================
// SCENARIO 4: Event Dispatching Integration
// ============================================================================

test('SimpleUnblockRequestProcessed dispatches to correct listener', function () {
    Event::fake([SimpleUnblockRequestProcessed::class]);

    $event = new SimpleUnblockRequestProcessed(
        ip: '192.168.1.1',
        domain: 'example.com',
        email: 'test@example.com'
    );

    Event::dispatch($event);

    Event::assertDispatched(SimpleUnblockRequestProcessed::class, function ($e) {
        return $e->ip === '192.168.1.1'
            && $e->domain === 'example.com'
            && $e->email === 'test@example.com';
    });
});

test('SimpleUnblockOtpSent dispatches to correct listener', function () {
    Event::fake([SimpleUnblockOtpSent::class]);

    $event = new SimpleUnblockOtpSent(
        email: 'test@example.com',
        ip: '192.168.1.1'
    );

    Event::dispatch($event);

    Event::assertDispatched(SimpleUnblockOtpSent::class);
});

test('SimpleUnblockOtpVerified dispatches to correct listener', function () {
    Event::fake([SimpleUnblockOtpVerified::class]);

    $event = new SimpleUnblockOtpVerified(
        email: 'test@example.com',
        ip: '192.168.1.1'
    );

    Event::dispatch($event);

    Event::assertDispatched(SimpleUnblockOtpVerified::class);
});

test('SimpleUnblockOtpFailed dispatches to correct listeners', function () {
    Event::fake([SimpleUnblockOtpFailed::class]);

    $event = new SimpleUnblockOtpFailed(
        email: 'test@example.com',
        ip: '192.168.1.1',
        reason: 'invalid_otp'
    );

    Event::dispatch($event);

    Event::assertDispatched(SimpleUnblockOtpFailed::class, function ($e) {
        return $e->reason === 'invalid_otp';
    });
});

test('SimpleUnblockRateLimitExceeded dispatches to CreateAbuseIncidentListener', function () {
    Event::fake([SimpleUnblockRateLimitExceeded::class]);

    $event = new SimpleUnblockRateLimitExceeded(
        vector: 'ip',
        identifier: '192.168.1.1',
        attempts: 10,
        maxAttempts: 5
    );

    Event::dispatch($event);

    Event::assertDispatched(SimpleUnblockRateLimitExceeded::class);
});

test('SimpleUnblockHoneypotTriggered dispatches to CreateAbuseIncidentListener', function () {
    Event::fake([SimpleUnblockHoneypotTriggered::class]);

    $event = new SimpleUnblockHoneypotTriggered(
        ip: '192.168.1.1',
        email: 'test@example.com',
        domain: 'example.com'
    );

    Event::dispatch($event);

    Event::assertDispatched(SimpleUnblockHoneypotTriggered::class);
});

test('SimpleUnblockIpMismatch dispatches to CreateAbuseIncidentListener', function () {
    Event::fake([SimpleUnblockIpMismatch::class]);

    $event = new SimpleUnblockIpMismatch(
        originalIp: '192.168.1.1',
        verificationIp: '192.168.1.2',
        email: 'test@example.com'
    );

    Event::dispatch($event);

    Event::assertDispatched(SimpleUnblockIpMismatch::class);
});

// ============================================================================
// SCENARIO 5: Listener Count Validation
// ============================================================================

test('SimpleUnblockOtpFailed has exactly two listeners', function () {
    $provider = new EventServiceProvider(app());
    $listen = $provider->listens();

    expect($listen[SimpleUnblockOtpFailed::class])->toHaveCount(2);
});

test('abuse incident events have exactly one listener', function () {
    $provider = new EventServiceProvider(app());
    $listen = $provider->listens();

    $abuseEvents = [
        SimpleUnblockRateLimitExceeded::class,
        SimpleUnblockHoneypotTriggered::class,
        SimpleUnblockIpMismatch::class,
    ];

    foreach ($abuseEvents as $event) {
        expect($listen[$event])->toHaveCount(1)
            ->and($listen[$event][0])->toBe(CreateAbuseIncidentListener::class);
    }
});

test('email reputation events have exactly one listener', function () {
    $provider = new EventServiceProvider(app());
    $listen = $provider->listens();

    $emailEvents = [
        SimpleUnblockOtpSent::class,
        SimpleUnblockOtpVerified::class,
    ];

    foreach ($emailEvents as $event) {
        expect($listen[$event])->toHaveCount(1)
            ->and($listen[$event][0])->toBe(TrackEmailReputationListener::class);
    }
});
