<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Support\Facades\{App, Auth};
use Livewire\Component;

/**
 * Language Switcher Component
 *
 * Provides a UI to switch between available languages.
 * - For authenticated users: Updates DB preference
 * - For guests: Stores in session
 */
class LanguageSwitcher extends Component
{
    /**
     * Available locales
     */
    public array $availableLocales = [
        'es' => ['name' => 'Español', 'flag' => '🇪🇸'],
        'en' => ['name' => 'English', 'flag' => '🇬🇧'],
    ];

    /**
     * Current locale
     */
    public string $currentLocale;

    /**
     * Mount component
     */
    public function mount(): void
    {
        // Get current locale (already set by middleware)
        $this->currentLocale = App::getLocale();
    }

    /**
     * Change the application locale
     */
    public function changeLocale(string $locale): void
    {
        // Validate locale
        if (! array_key_exists($locale, $this->availableLocales)) {
            return;
        }

        // Update for authenticated users
        if (Auth::check()) {
            $user = Auth::user();
            $user->update(['preferred_locale' => $locale]);

            // Refresh user model to ensure changes are loaded in Auth facade
            Auth::setUser($user->fresh());
        }

        // Store in session for all users
        session()->put('locale', $locale);

        // Force session save
        session()->save();

        // Set immediately for current request
        App::setLocale($locale);
        $this->currentLocale = $locale;

        // Dispatch browser event for UI updates
        $this->dispatch('locale-changed', locale: $locale);

        // Show success message
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('common.language_changed'),
        ]);

        // Force full page reload (not SPA navigation) to apply translations via middleware
        // Use JavaScript redirect to ensure full page reload in all browsers
        // Validate Referer belongs to this application to prevent open redirect / JS injection
        $referer = request()->header('Referer');
        $appUrl = config('app.url');
        $redirectUrl = ($referer && str_starts_with($referer, $appUrl))
            ? $referer
            : route('dashboard');

        // Use JavaScript to force a full page reload
        // json_encode safely escapes the URL for embedding in JavaScript
        $safeUrl = json_encode($redirectUrl, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->js("window.location.href = {$safeUrl};");
    }

    /**
     * Render component
     */
    public function render()
    {
        return view('livewire.language-switcher');
    }
}
