<?php

declare(strict_types=1);

use App\Actions\SimpleUnblockAction;
use App\Jobs\ProcessSimpleUnblockJob;
use App\Models\{Account, Domain, Host};
use Illuminate\Support\Facades\{Config, Queue, RateLimiter};

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    Queue::fake();

    Config::set('unblock.simple_mode.throttle_email_per_hour', 5);
    Config::set('unblock.simple_mode.throttle_domain_per_hour', 10);

    // Create test host
    $this->host = Host::factory()->create();

    // Create test account
    $this->account = Account::factory()->create([
        'host_id' => $this->host->id,
        'username' => 'testuser',
        'domain' => 'example.com',
    ]);

    // Create test domains (Phase 3 requirement)
    $this->domain = Domain::factory()->create([
        'account_id' => $this->account->id,
        'domain_name' => 'example.com',
        'type' => 'primary',
    ]);

    Domain::factory()->create([
        'account_id' => $this->account->id,
        'domain_name' => 'sub.example.com',
        'type' => 'subdomain',
    ]);

    // Clear rate limiters
    RateLimiter::clear('simple_unblock:email:'.hash('sha256', 'test@example.com'));
    RateLimiter::clear('simple_unblock:domain:example.com');
});

afterEach(function () {
    // Clear rate limiters
    RateLimiter::clear('simple_unblock:email:'.hash('sha256', 'test@example.com'));
    RateLimiter::clear('simple_unblock:domain:example.com');
});

test('action normalizes domain to lowercase', function () {
    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'EXAMPLE.COM',
        email: 'test@example.com'
    );

    Queue::assertPushed(ProcessSimpleUnblockJob::class, function ($job) {
        return $job->domain === 'example.com';
    });
});

test('action removes www prefix from domain', function () {
    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'www.example.com',
        email: 'test@example.com'
    );

    Queue::assertPushed(ProcessSimpleUnblockJob::class, function ($job) {
        return $job->domain === 'example.com';
    });
});

test('action validates domain format', function () {
    expect(fn () => SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'invalid domain!',
        email: 'test@example.com'
    ))->toThrow(\InvalidArgumentException::class);
});

test('action dispatches job for specific host only (Phase 3)', function () {
    // Phase 3: Now only dispatches 1 job for the specific host where domain is hosted
    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'example.com',
        email: 'test@example.com'
    );

    Queue::assertPushed(ProcessSimpleUnblockJob::class, 1);
});

test('action dispatches job with correct parameters for domain host', function () {
    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'example.com',
        email: 'test@example.com'
    );

    Queue::assertPushed(ProcessSimpleUnblockJob::class, function ($job) {
        return $job->ip === '192.168.1.1'
            && $job->domain === 'example.com'
            && $job->email === 'test@example.com'
            && $job->hostId === $this->host->id; // Uses domain's host
    });
});

test('action logs activity', function () {
    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'example.com',
        email: 'test@example.com'
    );

    assertDatabaseHas('activity_log', [
        'description' => 'simple_unblock_request',
    ]);
});

test('action handles domain not found gracefully (Phase 3)', function () {
    // Phase 3: If domain doesn't exist in local DB, no unblock job is dispatched
    // but admin notification is sent
    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'nonexistent.com', // Domain not in database
        email: 'test@example.com'
    );

    // Should not dispatch unblock job
    Queue::assertNotPushed(ProcessSimpleUnblockJob::class);

    // Should dispatch admin notification job
    Queue::assertPushed(\App\Jobs\SendSimpleUnblockNotificationJob::class);
});

// NOTE: Tests for null account/host cannot be automated with SQLite foreign key constraints
// The validation logic in SimpleUnblockAction (lines 78-105) handles these edge cases
// by logging errors and notifying admins. Production logs will show if these occur.

test('action handles multiple domain formats', function () {
    $testCases = [
        ['input' => 'example.com', 'expected' => 'example.com'],
        ['input' => 'EXAMPLE.COM', 'expected' => 'example.com'],
        ['input' => 'www.example.com', 'expected' => 'example.com'],
        ['input' => 'WWW.EXAMPLE.COM', 'expected' => 'example.com'],
        ['input' => 'sub.example.com', 'expected' => 'sub.example.com'],
    ];

    foreach ($testCases as $testCase) {
        Queue::fake();

        SimpleUnblockAction::run(
            ip: '192.168.1.1',
            domain: $testCase['input'],
            email: 'test@example.com'
        );

        Queue::assertPushed(ProcessSimpleUnblockJob::class, function ($job) use ($testCase) {
            return $job->domain === $testCase['expected'];
        });
    }
});

test('action rejects invalid domain formats', function () {
    $invalidDomains = [
        'invalid',
        'invalid domain',
        '192.168.1.1',
        'http://example.com',
        'example',
    ];

    foreach ($invalidDomains as $invalidDomain) {
        expect(fn () => SimpleUnblockAction::run(
            ip: '192.168.1.1',
            domain: $invalidDomain,
            email: 'test@example.com'
        ))->toThrow(\InvalidArgumentException::class);
    }
});

// v1.1.1: Email and Domain rate limiting tests

test('email rate limiting blocks excessive requests', function () {
    Config::set('unblock.simple_mode.throttle_email_per_hour', 3);

    // First 3 requests should succeed
    for ($i = 0; $i < 3; $i++) {
        SimpleUnblockAction::run(
            ip: '192.168.1.1',
            domain: 'example.com',
            email: 'test@example.com'
        );
    }

    // 4th request should throw exception
    expect(fn () => SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'example.com',
        email: 'test@example.com'
    ))->toThrow(\RuntimeException::class);
});

test('domain rate limiting blocks excessive requests', function () {
    Config::set('unblock.simple_mode.throttle_domain_per_hour', 3);

    // First 3 requests should succeed
    for ($i = 0; $i < 3; $i++) {
        SimpleUnblockAction::run(
            ip: "192.168.1.{$i}",
            domain: 'example.com',
            email: "user{$i}@example.com"
        );

        // Clear email limits to test domain limit only
        RateLimiter::clear('simple_unblock:email:'.hash('sha256', "user{$i}@example.com"));
    }

    // 4th request should throw exception
    expect(fn () => SimpleUnblockAction::run(
        ip: '192.168.1.99',
        domain: 'example.com',
        email: 'user99@example.com'
    ))->toThrow(\RuntimeException::class);
});

test('different emails have separate rate limits', function () {
    Config::set('unblock.simple_mode.throttle_email_per_hour', 2);

    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'example.com',
        email: 'user1@example.com'
    );

    SimpleUnblockAction::run(
        ip: '192.168.1.2',
        domain: 'example.com',
        email: 'user2@example.com'
    );

    // Both should succeed because they're different emails
    Queue::assertPushed(ProcessSimpleUnblockJob::class, 2);
});

test('activity log contains hashed email', function () {
    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'example.com',
        email: 'test@example.com'
    );

    $log = \Spatie\Activitylog\Models\Activity::where('description', 'simple_unblock_request')
        ->latest()
        ->first();

    expect($log->properties)->toHaveKey('email_hash');
    expect($log->properties['email_hash'])->toBe(hash('sha256', 'test@example.com'));
});

test('activity log contains email domain', function () {
    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'example.com',
        email: 'test@example.com'
    );

    $log = \Spatie\Activitylog\Models\Activity::where('description', 'simple_unblock_request')
        ->latest()
        ->first();

    expect($log->properties)->toHaveKey('email_domain');
    expect($log->properties['email_domain'])->toBe('example.com');
});

test('activity log does NOT contain plaintext email', function () {
    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'example.com',
        email: 'test@example.com'
    );

    $log = \Spatie\Activitylog\Models\Activity::where('description', 'simple_unblock_request')
        ->latest()
        ->first();

    expect($log->properties)->not->toHaveKey('email');
});

test('rate limit exception logs contain hashed email', function () {
    Config::set('unblock.simple_mode.throttle_email_per_hour', 1);

    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'example.com',
        email: 'test@example.com'
    );

    try {
        SimpleUnblockAction::run(
            ip: '192.168.1.1',
            domain: 'example.com',
            email: 'test@example.com'
        );
    } catch (\RuntimeException $e) {
        // Expected
    }

    $log = \Spatie\Activitylog\Models\Activity::where('description', 'simple_unblock_rate_limit_exceeded')
        ->where('properties->vector', 'Email')
        ->latest()
        ->first();

    expect($log)->not->toBeNull();
    expect($log->properties)->toHaveKey('email_hash');
    expect($log->properties)->not->toHaveKey('email');
});
