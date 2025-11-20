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
        $redirectUrl = request()->header('Referer') ?? route('dashboard');

        // Use JavaScript to force a full page reload
        $this->js("window.location.href = '{$redirectUrl}';");
    }

    /**
     * Render component
     */
    public function render()
    {
        return view('livewire.language-switcher');
    }
}
