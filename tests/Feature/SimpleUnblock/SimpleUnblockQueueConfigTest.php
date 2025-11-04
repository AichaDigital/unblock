<?php

declare(strict_types=1);

use App\Actions\SimpleUnblockAction;
use App\Jobs\ProcessSimpleUnblockJob;
use App\Models\{Account, Domain, Host};
use Illuminate\Support\Facades\{Bus, Config, Queue, RateLimiter};

/**
 * Tests for SimpleUnblock with different queue configurations
 *
 * This test file ensures SimpleUnblock works correctly with:
 * - SYNC queue mode (immediate execution)
 * - DATABASE queue mode (queued execution)
 * - Different rate limit configurations
 *
 * These tests use explicit config() calls to avoid depending on .env.testing
 */
beforeEach(function () {
    // Create hosts for testing
    $this->host = Host::factory()->create(['panel' => 'cpanel']);

    // Create test account
    $this->account = Account::factory()->create([
        'host_id' => $this->host->id,
        'username' => 'testuser',
        'domain' => 'example.com',
    ]);

    // Create test domain
    $this->domain = Domain::factory()->create([
        'account_id' => $this->account->id,
        'domain_name' => 'example.com',
        'type' => 'primary',
    ]);

    // Clear rate limiters
    RateLimiter::clear('simple_unblock:email:'.hash('sha256', 'test@example.com'));
    RateLimiter::clear('simple_unblock:domain:example.com');
});

test('simple unblock works in SYNC queue mode', function () {
    // Configure for SYNC mode explicitly
    Config::set('queue.default', 'sync');
    Config::set('unblock.simple_mode.enabled', true);
    Config::set('unblock.simple_mode.throttle_email_per_hour', 10);
    Config::set('unblock.simple_mode.throttle_domain_per_hour', 20);

    Bus::fake();

    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'example.com',
        email: 'test@example.com'
    );

    // In sync mode, job should be dispatched
    Bus::assertDispatched(ProcessSimpleUnblockJob::class);
});

test('simple unblock works in DATABASE queue mode', function () {
    // Configure for DATABASE mode explicitly
    Config::set('queue.default', 'database');
    Config::set('unblock.simple_mode.enabled', true);
    Config::set('unblock.simple_mode.throttle_email_per_hour', 10);
    Config::set('unblock.simple_mode.throttle_domain_per_hour', 20);

    Queue::fake();

    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'example.com',
        email: 'test@example.com'
    );

    // Job should be queued
    Queue::assertPushed(ProcessSimpleUnblockJob::class);
});

test('rate limiting respects explicit configuration', function () {
    // Set very low rate limit explicitly
    Config::set('unblock.simple_mode.enabled', true);
    Config::set('unblock.simple_mode.throttle_email_per_hour', 2);
    Config::set('queue.default', 'sync');

    Queue::fake();

    // First 2 requests should succeed
    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'example.com',
        email: 'test@example.com'
    );

    SimpleUnblockAction::run(
        ip: '192.168.1.2',
        domain: 'example.com',
        email: 'test@example.com'
    );

    // 3rd should fail
    expect(fn () => SimpleUnblockAction::run(
        ip: '192.168.1.3',
        domain: 'example.com',
        email: 'test@example.com'
    ))->toThrow(\RuntimeException::class);
});

test('simple unblock can be disabled via configuration', function () {
    // Note: Simple mode doesn't have a global "enabled" check at the action level
    // It's controlled at the route/middleware level
    // This test validates that configuration can be checked if needed

    Config::set('unblock.simple_mode.enabled', false);

    // SimpleUnblock will still execute if called directly
    // In production, the route middleware handles the "enabled" check
    expect(Config::get('unblock.simple_mode.enabled'))->toBeFalse();
})->skip('Simple mode enabled/disabled is handled at route level, not action level');

test('notification behavior works correctly with explicit queue configuration', function () {
    // Test with DATABASE mode explicitly configured
    Config::set('queue.default', 'database');
    Config::set('unblock.simple_mode.enabled', true);

    // Clear rate limiter for this test
    RateLimiter::clear('simple_unblock:email:'.hash('sha256', 'queuetest@example.com'));

    Queue::fake();

    SimpleUnblockAction::run(
        ip: '192.168.1.99',
        domain: 'example.com',
        email: 'queuetest@example.com'
    );

    // Job should be dispatched to queue
    Queue::assertPushed(ProcessSimpleUnblockJob::class, function ($job) {
        return $job->email === 'queuetest@example.com';
    });
});

