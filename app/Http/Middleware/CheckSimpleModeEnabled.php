<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Check Simple Mode Enabled Middleware
 *
 * Ensures that simple unblock routes are only accessible when simple mode is enabled.
 * Returns 404 when disabled to avoid leaking information about the feature.
 */
class CheckSimpleModeEnabled
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('unblock.simple_mode.enabled', false)) {
            abort(404);
        }

        return $next($request);
    }
}
