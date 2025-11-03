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

        // Skip if not authenticated (will be handled by Authenticate middleware)
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Skip if not admin (will be handled by VerifyIsAdminMiddleware)
        if (! $user->canAccessPanel()) {
            return $next($request);
        }

        // Check if OTP is already verified in this session
        $otpVerified = session()->get('admin_otp_verified');
        $otpUserId = session()->get('admin_otp_user_id');
        $otpVerifiedAt = session()->get('admin_otp_verified_at');

        // Check if OTP verification has expired
        $sessionTtl = config('unblock.admin_otp.session_ttl', 28800); // 8 hours default
        if ($otpVerifiedAt && now()->timestamp - $otpVerifiedAt > $sessionTtl) {
            // OTP verification expired - logout completely (password + OTP both invalidated)
            Log::info('Admin OTP session expired, logging out', [
                'user_id' => $user->id,
                'email' => $user->email,
                'expired_at' => $otpVerifiedAt,
            ]);

            session()->flush();
            Auth::logout();

            return redirect()->route('filament.admin.auth.login')
                ->with('status', __('admin_otp.session_expired'));
        }

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
