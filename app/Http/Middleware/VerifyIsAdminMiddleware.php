<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerifyIsAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            return to_route('login');
        }

        $user = Auth::user();

        if (! $user) {
            return to_route('login');
        }

        if (! $user->is_admin || $user->trashed()) {
            return to_route('dashboard');
        }

        return $next($request);
    }
}
