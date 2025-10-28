<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Simple Mode Access Middleware
 *
 * Allows access to simple unblock form for temporary users created in simple mode.
 * Prevents access to normal dashboard and admin areas.
 */
class SimpleModeAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to authenticated users
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Check if this is a temporary user created in simple mode
        $isSimpleModeUser = $user->first_name === 'Simple' &&
                           $user->last_name === 'Unblock' &&
                           ! $user->is_admin;

        if ($isSimpleModeUser) {
            // Allow access to simple unblock form
            if ($request->routeIs('simple.unblock')) {
                return $next($request);
            }

            // Redirect simple mode users away from normal dashboard
            if ($request->routeIs('dashboard')) {
                return redirect()->route('simple.unblock');
            }

            // Block access to admin areas
            if ($request->is('admin/*')) {
                abort(403, 'Access denied for simple mode users');
            }
        }

        return $next($request);
    }
}
