<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * User Profile Page - Language Preference
 */
class UserProfile extends Page implements HasForms
{
    use InteractsWithForms;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected string $view = 'filament.pages.user-profile';

    protected static ?string $title = 'My Profile';

    protected static ?int $navigationSort = 1000;

    public static function getNavigationGroup(): ?string
    {
        return 'Account';
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
                    ->label('Language Preference')
                    ->options([
                        'es' => '🇪🇸 Español',
                        'en' => '🇬🇧 English',
                    ])
                    ->required()
                    ->native(false)
                    ->helperText('Select your preferred language for the admin panel'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Auth::user()->update([
            'preferred_locale' => $data['preferred_locale'],
        ]);

        // Update session immediately
        session()->put('locale', $data['preferred_locale']);
        app()->setLocale($data['preferred_locale']);

        Notification::make()
            ->success()
            ->title('Language preference updated')
            ->body('Your language preference has been saved successfully.')
            ->send();

        // Refresh page to apply translations
        redirect()->to(static::getUrl());
    }
}

