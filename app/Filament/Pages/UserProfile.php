<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

/**
 * User Profile Page - Language Preference
 */
class UserProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected string $view = 'filament.pages.user-profile';

    protected static ?string $title = null;

    protected static ?int $navigationSort = 1000;

    public static function getNavigationLabel(): string
    {
        return __('user_profile.navigation_label');
    }

    public function getTitle(): string|Htmlable
    {
        return __('user_profile.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('user_profile.navigation_group');
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'preferred_locale' => Auth::user()->preferred_locale ?? 'es',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('preferred_locale')
                    ->label(__('user_profile.fields.preferred_locale.label'))
                    ->options([
                        'es' => '🇪🇸 Español',
                        'en' => '🇬🇧 English',
                    ])
                    ->required()
                    ->native(false)
                    ->helperText(__('user_profile.fields.preferred_locale.helper')),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $newLocale = $data['preferred_locale'];

        // Update user preference in database
        $user = Auth::user();
        $user->update([
            'preferred_locale' => $newLocale,
        ]);

        // Refresh user model to ensure changes are loaded in Auth facade
        Auth::setUser($user->fresh());

        // Update session immediately
        session()->put('locale', $newLocale);

        // Set locale for current request
        app()->setLocale($newLocale);

        // Force session save
        session()->save();

        Notification::make()
            ->success()
            ->title(__('user_profile.notifications.saved.title'))
            ->body(__('user_profile.notifications.saved.body'))
            ->send();

        // Force full page reload (not SPA navigation) to apply translations via middleware
        $this->redirect(static::getUrl(), navigate: false);
    }
}
