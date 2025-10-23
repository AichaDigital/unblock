<?php

declare(strict_types=1);

use Illuminate\Support\Facades\{Config, RateLimiter, Route};

use function Pest\Laravel\{assertDatabaseHas, get};

beforeEach(function () {
    Config::set('unblock.simple_mode.enabled', true);
    Config::set('unblock.simple_mode.throttle_per_minute', 3);
    Config::set('unblock.simple_mode.throttle_subnet_per_hour', 20);
    Config::set('unblock.simple_mode.throttle_global_per_hour', 500);

    // Clear all rate limiter keys
    RateLimiter::clear('simple_unblock:ip:127.0.0.1');
    RateLimiter::clear('simple_unblock:subnet:127.0.0.0/24');
    RateLimiter::clear('simple_unblock:subnet:192.168.1.0/24'); // For subnet test
    RateLimiter::clear('simple_unblock:global');

    // Register simple unblock route for testing
    Route::get('/simple-unblock', \App\Livewire\SimpleUnblockForm::class)
        ->middleware(['throttle.simple.unblock'])
        ->name('simple.unblock');
});

afterEach(function () {
    // Clear all rate limiter keys
    RateLimiter::clear('simple_unblock:ip:127.0.0.1');
    RateLimiter::clear('simple_unblock:subnet:127.0.0.0/24');
    RateLimiter::clear('simple_unblock:subnet:192.168.1.0/24');
    RateLimiter::clear('simple_unblock:global');
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
    $response = get('/simple-unblock');
    $response->assertStatus(429);
    expect($response->json('retry_after'))->toBeGreaterThan(0);
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

    // Clear ALL rate limiter keys (simulating time passage)
    RateLimiter::clear('simple_unblock:ip:127.0.0.1');
    RateLimiter::clear('simple_unblock:subnet:127.0.0.0/24');
    RateLimiter::clear('simple_unblock:global');

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

    // v1.1.1: New key format with 'ip:' prefix
    expect(RateLimiter::attempts("simple_unblock:ip:{$ip}"))->toBeGreaterThan(0);
});

// v1.1.1: New multi-vector rate limiting tests

test('subnet rate limiting blocks excessive requests from same subnet', function () {
    Config::set('unblock.simple_mode.throttle_subnet_per_hour', 5); // Lower for testing
    RateLimiter::clear('simple_unblock:global'); // Clear global limit

    // Simulate 6 requests from different IPs in same subnet
    for ($i = 1; $i <= 6; $i++) {
        RateLimiter::clear("simple_unblock:ip:192.168.1.{$i}"); // Clear IP limits
        $this->withServerVariables(['REMOTE_ADDR' => "192.168.1.{$i}"]);

        $response = get('/simple-unblock');

        if ($i <= 5) {
            $response->assertOk();
        } else {
            $response->assertStatus(429);
        }
    }
});

test('global rate limiting prevents DDoS attacks', function () {
    Config::set('unblock.simple_mode.throttle_global_per_hour', 3); // Lower for testing

    // Simulate 4 requests from different IPs and subnets
    $ips = ['10.0.0.1', '20.0.0.1', '30.0.0.1', '40.0.0.1'];

    foreach ($ips as $index => $ip) {
        RateLimiter::clear("simple_unblock:ip:{$ip}"); // Clear IP limits
        RateLimiter::clear('simple_unblock:subnet:'.preg_replace('/\d+$/', '0/24', $ip)); // Clear subnet limits

        $this->withServerVariables(['REMOTE_ADDR' => $ip]);
        $response = get('/simple-unblock');

        if ($index < 3) {
            $response->assertOk();
        } else {
            $response->assertStatus(429);
        }
    }
});

test('rate limit logs include vector information', function () {
    Config::set('unblock.simple_mode.throttle_per_minute', 1);

    // Exceed IP limit
    get('/simple-unblock');
    get('/simple-unblock');

    assertDatabaseHas('activity_log', [
        'description' => 'simple_unblock_rate_limit_exceeded',
    ]);

    // Check properties include vector
    $log = \Spatie\Activitylog\Models\Activity::where('description', 'simple_unblock_rate_limit_exceeded')
        ->latest()
        ->first();

    expect($log->properties)->toHaveKey('vector');
});

test('IPv4 subnet calculation is correct', function () {
    $middleware = new \App\Http\Middleware\ThrottleSimpleUnblock;
    $reflection = new \ReflectionClass($middleware);
    $method = $reflection->getMethod('getSubnet');
    $method->setAccessible(true);

    expect($method->invoke($middleware, '192.168.1.100'))->toBe('192.168.1.0/24');
    expect($method->invoke($middleware, '10.20.30.40'))->toBe('10.20.30.0/24');
});

test('IPv6 subnet calculation is correct', function () {
    $middleware = new \App\Http\Middleware\ThrottleSimpleUnblock;
    $reflection = new \ReflectionClass($middleware);
    $method = $reflection->getMethod('getSubnet');
    $method->setAccessible(true);

    expect($method->invoke($middleware, '2001:0db8:85a3:0000:0000:8a2e:0370:7334'))
        ->toBe('2001:0db8:85a3::/48');
});
