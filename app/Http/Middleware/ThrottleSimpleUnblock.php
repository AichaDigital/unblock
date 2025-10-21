<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Log, RateLimiter};
use Symfony\Component\HttpFoundation\Response;

/**
 * Throttle Simple Unblock Middleware
 *
 * Aggressive rate limiting for anonymous simple unblock requests.
 * Uses IP-based tracking instead of session-based.
 */
class ThrottleSimpleUnblock
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $key = "simple_unblock:{$ip}";
        $maxAttempts = config('unblock.simple_mode.throttle_per_minute', 3);
        $decayMinutes = 1;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            activity()
                ->withProperties([
                    'ip' => $ip,
                    'attempts' => RateLimiter::attempts($key),
                    'user_agent' => $request->userAgent(),
                    'referer' => $request->header('referer'),
                ])
                ->log('simple_unblock_rate_limit_exceeded');

            Log::warning('Simple unblock rate limit exceeded', [
                'ip' => $ip,
                'attempts' => RateLimiter::attempts($key),
                'retry_after' => $seconds,
            ]);

            return response()->json([
                'error' => __('simple_unblock.rate_limit_exceeded', ['seconds' => $seconds]),
                'retry_after' => $seconds,
            ], 429);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($key, $maxAttempts));

        return $response;
    }
}
