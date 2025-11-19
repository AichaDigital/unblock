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

        return $next($request);
    }

    /**
     * Determine the locale to use
     */
    private function determineLocale(Request $request): string
    {
        // 1. Authenticated user's preference (highest priority)
        if (Auth::check() && Auth::user()->preferred_locale) {
            $userLocale = Auth::user()->preferred_locale;

            // Validate user's locale is in available list
            if ($this->isValidLocale($userLocale)) {
                return $userLocale;
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
