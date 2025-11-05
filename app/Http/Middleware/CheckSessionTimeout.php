<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Session};

class CheckSessionTimeout
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $lastActivity = Session::get('last_activity', now()->timestamp);

            // Different timeout for admins vs regular users
            $timeoutMinutes = $user->is_admin ? 480 : 240; // Admin: 8 hours, Users: 4 hours
            $sessionTimeout = $timeoutMinutes * 60; // Convert minutes to seconds

            // Check if session has expired
            if (now()->timestamp - $lastActivity > $sessionTimeout) {
                // Log the session expiration for security auditing
                activity('session_timeout')
                    ->performedOn($user)
                    ->causedBy($user)
                    ->withProperties([
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'last_activity' => date('Y-m-d H:i:s', $lastActivity),
                        'expired_at' => now()->toDateTimeString(),
                        'user_type' => $user->is_admin ? 'admin' : 'user',
                        'timeout_minutes' => $timeoutMinutes,
                    ])
                    ->log('Session expired due to inactivity');

                // Logout user and redirect to appropriate login based on user type
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                // CRITICAL: Admins should return to admin login, regular users to normal login
                $redirectRoute = $user->is_admin ? 'filament.admin.auth.login' : 'login';

                return redirect()->route($redirectRoute)->with('message', __('messages.session_expired'));
            }

            // Update last activity timestamp
            Session::put('last_activity', now()->timestamp);
        }

        return $next($request);
    }
}
