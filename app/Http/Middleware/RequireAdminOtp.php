<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Log};
use Symfony\Component\HttpFoundation\Response;

/**
 * Require Admin OTP Middleware
 *
 * Enforces OTP verification for admin panel access.
 * After successful password authentication, admin must verify OTP code sent via email.
 */
class RequireAdminOtp
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if admin OTP is disabled
        if (! config('unblock.admin_otp.enabled', true)) {
            return $next($request);
        }

        // Check if user is authenticated
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Skip if not admin (VerifyIsAdminMiddleware will handle this)
        if (! $user->is_admin) {
            return $next($request);
        }

        // CRITICAL: Detect and handle invalid session states
        // If user has cookies but no valid session data, force full logout
        $sessionId = session()->getId();
        $lastActivity = session()->get('last_activity');

        // Check if session is stale or invalid
        if (! $sessionId || ! $lastActivity) {
            Log::warning('Admin detected with invalid session, forcing logout', [
                'user_id' => $user->id,
                'email' => $user->email,
                'has_session_id' => (bool) $sessionId,
                'has_last_activity' => (bool) $lastActivity,
                'ip' => $request->ip(),
            ]);

            // Force complete cleanup
            session()->invalidate();
            session()->regenerateToken();
            Auth::logout();

            // Clear all authentication cookies
            $request->session()->flush();

            return redirect()->route('filament.admin.auth.login')
                ->withCookie(cookie()->forget('remember_web'))
                ->withCookie(cookie()->forget(config('session.cookie')))
                ->with('status', __('admin_otp.session_invalid'));
        }

        // Check if OTP is already verified in this session
        $otpVerified = session()->get('admin_otp_verified');
        $otpUserId = session()->get('admin_otp_user_id');

        // Laravel handles session expiration automatically via SESSION_LIFETIME
        // No need to check custom TTL - if session is alive, OTP verification is valid
        // If session expires, user must re-authenticate (password + OTP)

        // If OTP is verified and matches current user, allow access
        if ($otpVerified && $otpUserId === $user->id) {
            return $next($request);
        }

        // Check if we're already on the OTP verification page (avoid redirect loop)
        if ($request->routeIs('admin.otp.verify')) {
            return $next($request);
        }

        // OTP not verified - send OTP and redirect to verification page
        Log::info('Admin OTP required', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        // Send OTP code
        try {
            $user->sendOneTimePassword();

            // Store pending OTP user in session
            session()->put('admin_otp_pending_user_id', $user->id);
            session()->put('admin_otp_sent_at', now()->timestamp);

            session()->flash('otp_sent', true);
        } catch (\Exception $e) {
            Log::error('Failed to send admin OTP', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Redirect to OTP verification page
        return redirect()->route('admin.otp.verify');
    }
}
