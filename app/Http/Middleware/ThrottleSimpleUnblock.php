<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Log, RateLimiter};
use Symfony\Component\HttpFoundation\Response;

/**
 * Throttle Simple Unblock Middleware (v1.2.0)
 *
 * Multi-vector rate limiting for anonymous simple unblock requests:
 * - Per IP
 * - Per Subnet (/24)
 * - Global
 *
 * Email and Domain throttling are handled in SimpleUnblockAction
 * where those values are available.
 */
class ThrottleSimpleUnblock
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        // Vector 1: Per IP (3 req/min)
        $exceeded = $this->checkRateLimit(
            key: "simple_unblock:ip:{$ip}",
            maxAttempts: config('unblock.simple_mode.throttle_per_minute', 3),
            decaySeconds: 60,
            vectorName: 'IP'
        );

        if ($exceeded) {
            return $this->rateLimitResponse($ip, 'IP', $exceeded['seconds']);
        }

        // Vector 2: Per Subnet /24 (20 req/hour)
        $subnet = $this->getSubnet($ip);
        $exceeded = $this->checkRateLimit(
            key: "simple_unblock:subnet:{$subnet}",
            maxAttempts: config('unblock.simple_mode.throttle_subnet_per_hour', 20),
            decaySeconds: 3600,
            vectorName: 'Subnet'
        );

        if ($exceeded) {
            return $this->rateLimitResponse($ip, 'Subnet', $exceeded['seconds']);
        }

        // Vector 3: Global (500 req/hour)
        $exceeded = $this->checkRateLimit(
            key: 'simple_unblock:global',
            maxAttempts: config('unblock.simple_mode.throttle_global_per_hour', 500),
            decaySeconds: 3600,
            vectorName: 'Global'
        );

        if ($exceeded) {
            return $this->rateLimitResponse($ip, 'Global', $exceeded['seconds']);
        }

        // Proceed with request
        $response = $next($request);

        // Add rate limit headers
        $response->headers->set('X-RateLimit-Limit', (string) config('unblock.simple_mode.throttle_per_minute', 3));
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining("simple_unblock:ip:{$ip}", 3));

        return $response;
    }

    /**
     * Check rate limit for a given key
     */
    private function checkRateLimit(string $key, int $maxAttempts, int $decaySeconds, string $vectorName): ?array
    {
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            Log::warning("Simple unblock rate limit exceeded [{$vectorName}]", [
                'key' => $key,
                'vector' => $vectorName,
                'attempts' => RateLimiter::attempts($key),
                'max_attempts' => $maxAttempts,
                'retry_after' => $seconds,
            ]);

            return ['seconds' => $seconds];
        }

        RateLimiter::hit($key, $decaySeconds);

        return null;
    }

    /**
     * Return rate limit exceeded response
     */
    private function rateLimitResponse(string $ip, string $vector, int $seconds): Response
    {
        activity()
            ->withProperties([
                'ip' => $ip,
                'vector' => $vector,
                'user_agent' => request()->userAgent(),
                'referer' => request()->header('referer'),
            ])
            ->log('simple_unblock_rate_limit_exceeded');

        return response()->json([
            'error' => __('simple_unblock.rate_limit_exceeded', ['seconds' => $seconds]),
            'retry_after' => $seconds,
        ], 429);
    }

    /**
     * Get subnet /24 from IP address
     */
    private function getSubnet(string $ip): string
    {
        // IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);

            return "{$parts[0]}.{$parts[1]}.{$parts[2]}.0/24";
        }

        // IPv6 - Get first 48 bits (standard /48 prefix)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);

            return "{$parts[0]}:{$parts[1]}:{$parts[2]}::/48";
        }

        return 'unknown';
    }
}
