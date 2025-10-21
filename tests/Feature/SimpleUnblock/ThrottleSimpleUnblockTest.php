<?php

declare(strict_types=1);

use Illuminate\Support\Facades\{Config, RateLimiter, Route};

use function Pest\Laravel\{assertDatabaseHas, get};

beforeEach(function () {
    Config::set('unblock.simple_mode.enabled', true);
    Config::set('unblock.simple_mode.throttle_per_minute', 3);

    // Clear rate limiter BEFORE each test
    RateLimiter::clear('simple_unblock:127.0.0.1');

    // Register simple unblock route for testing
    Route::get('/simple-unblock', \App\Livewire\SimpleUnblockForm::class)
        ->middleware(['throttle.simple.unblock'])
        ->name('simple.unblock');
});

afterEach(function () {
    // Clear rate limiter after each test to prevent state leakage
    RateLimiter::clear('simple_unblock:127.0.0.1');
});

test('simple unblock allows configured requests per minute', function () {
    $maxAttempts = config('unblock.simple_mode.throttle_per_minute');

    for ($i = 0; $i < $maxAttempts; $i++) {
        get('/simple-unblock')
            ->assertOk();
    }

    expect(true)->toBeTrue();
});

test('simple unblock blocks after exceeding rate limit', function () {
    $maxAttempts = config('unblock.simple_mode.throttle_per_minute');

    // Make maximum allowed requests
    for ($i = 0; $i < $maxAttempts; $i++) {
        get('/simple-unblock');
    }

    // Next request should be blocked
    get('/simple-unblock')
        ->assertStatus(429)
        ->assertJson([
            'error' => __('simple_unblock.rate_limit_exceeded', ['seconds' => RateLimiter::availableIn('simple_unblock:'.request()->ip())]),
        ]);
});

test('simple unblock logs excessive attempts', function () {
    $maxAttempts = config('unblock.simple_mode.throttle_per_minute');

    // Exceed rate limit
    for ($i = 0; $i <= $maxAttempts; $i++) {
        get('/simple-unblock');
    }

    assertDatabaseHas('activity_log', [
        'description' => 'simple_unblock_rate_limit_exceeded',
    ]);
});

test('simple unblock resets counter after decay period', function () {
    $maxAttempts = config('unblock.simple_mode.throttle_per_minute');

    // Make maximum allowed requests
    for ($i = 0; $i < $maxAttempts; $i++) {
        get('/simple-unblock');
    }

    // Clear rate limiter (simulating time passage)
    RateLimiter::clear('simple_unblock:'.request()->ip());

    // Should be allowed again
    get('/simple-unblock')
        ->assertOk();
});

test('simple unblock includes rate limit headers', function () {
    $response = get('/simple-unblock');

    $response->assertHeader('X-RateLimit-Limit');
    $response->assertHeader('X-RateLimit-Remaining');
});

test('simple unblock tracks attempts per IP', function () {
    $ip = '192.168.1.1';

    // Simulate request from specific IP
    $this->withServerVariables(['REMOTE_ADDR' => $ip]);

    get('/simple-unblock');

    // RateLimiter returns string, not int
    expect(RateLimiter::attempts("simple_unblock:{$ip}"))->toBeGreaterThan(0);
});
