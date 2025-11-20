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
            Auth::user()->update(['preferred_locale' => $locale]);
        }

        // Store in session for all users
        session()->put('locale', $locale);

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

        // Refresh page to apply translations
        $this->redirect(request()->header('Referer') ?? route('dashboard'), navigate: true);
    }

    /**
     * Render component
     */
    public function render()
    {
        return view('livewire.language-switcher');
    }
}
