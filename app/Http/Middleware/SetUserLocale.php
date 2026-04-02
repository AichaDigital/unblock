<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{App, Auth};
use Symfony\Component\HttpFoundation\Response;

/**
 * Set User Locale Middleware
 *
 * Sets the application locale based on:
 * 1. Authenticated user's preferred_locale (from database)
 * 2. Session locale (for unauthenticated users)
 * 3. Browser's preferred language (Accept-Language header)
 * 4. Application default (es)
 */
class SetUserLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->determineLocale($request);

        if ($this->isValidLocale($locale)) {
            App::setLocale($locale);
        }

        $response = $next($request);

        // Add debug headers to help diagnose locale issues
        if (config('app.debug')) {
            $response->headers->set('X-Locale', $locale);
            $response->headers->set('X-User-Locale', Auth::check() ? (Auth::user()->preferred_locale ?? 'null') : 'guest');
            $response->headers->set('X-Session-Locale', $request->session()->get('locale', 'null'));
        }

        return $response;
    }

    /**
     * Determine the locale to use
     */
    private function determineLocale(Request $request): string
    {
        // 1. Authenticated user's preference (highest priority)
        if (Auth::check()) {
            // Force refresh user model to get latest preferred_locale from database
            $user = Auth::user()?->fresh();
            if ($user && $user->preferred_locale) {
                $userLocale = $user->preferred_locale;

                // Validate user's locale is in available list
                if ($this->isValidLocale($userLocale)) {
                    return $userLocale;
                }
            }
        }

        // 2. Session locale (for temporary user selections)
        if ($request->session()->has('locale')) {
            $sessionLocale = $request->session()->get('locale');

            // Validate session locale is in available list
            if ($this->isValidLocale($sessionLocale)) {
                return $sessionLocale;
            }
        }

        // 3. Browser's preferred language
        $availableLocales = config('app.available_locales', ['es', 'en']);
        $browserLocale = $request->getPreferredLanguage($availableLocales);
        if ($browserLocale) {
            return $browserLocale;
        }

        // 4. Application default
        return config('app.locale', 'es');
    }

    /**
     * Check if locale is valid
     */
    private function isValidLocale(string $locale): bool
    {
        $availableLocales = config('app.available_locales', ['es', 'en']);

        return in_array($locale, $availableLocales, true);
    }
}
