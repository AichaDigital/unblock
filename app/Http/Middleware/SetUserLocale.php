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
     * Available locales in the application
     */
    private const AVAILABLE_LOCALES = ['es', 'en'];

    /**
     * Default locale
     */
    private const DEFAULT_LOCALE = 'es';

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
            return Auth::user()->preferred_locale;
        }

        // 2. Session locale (for temporary user selections)
        if ($request->session()->has('locale')) {
            return $request->session()->get('locale');
        }

        // 3. Browser's preferred language
        $browserLocale = $request->getPreferredLanguage(self::AVAILABLE_LOCALES);
        if ($browserLocale) {
            return $browserLocale;
        }

        // 4. Application default
        return self::DEFAULT_LOCALE;
    }

    /**
     * Check if locale is valid
     */
    private function isValidLocale(string $locale): bool
    {
        return in_array($locale, self::AVAILABLE_LOCALES, true);
    }
}
