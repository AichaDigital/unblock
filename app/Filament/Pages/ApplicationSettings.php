<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\{FileUpload, TextInput};
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\{Section, Tabs};
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class ApplicationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected string $view = 'filament.pages.application-settings';

    protected static ?string $title = null;

    protected static ?int $navigationSort = 999;

    public static function getNavigationLabel(): string
    {
        return __('application_settings.navigation_label');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('application_settings.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('application_settings.navigation_group');
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'company_logo_light' => setting('company_logo_light'),
            'company_logo_dark' => setting('company_logo_dark'),
            'company_name' => setting('company_name'),
            'support_email' => setting('support_email'),
            'support_url' => setting('support_url'),
            'privacy_policy_url' => setting('privacy_policy_url'),
            'terms_url' => setting('terms_url'),
            'data_protection_url' => setting('data_protection_url'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make(__('application_settings.tabs.settings'))
                    ->tabs([
                        Tabs\Tab::make(__('application_settings.tabs.branding'))
                            ->schema([
                                Section::make(__('application_settings.sections.company_branding.title'))
                                    ->description(__('application_settings.sections.company_branding.description'))
                                    ->schema([
                                        FileUpload::make('company_logo_light')
                                            ->label(__('application_settings.fields.company_logo_light.label'))
                                            ->image()
                                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml', 'image/webp'])
                                            ->maxSize(2048) // 2MB
                                            ->directory('company')
                                            ->disk('public')
                                            ->visibility('public')
                                            ->imageEditor()
                                            ->imageEditorAspectRatios([null, '1:1', '16:9'])
                                            ->imageResizeMode('contain')
                                            ->imageResizeTargetWidth('1000')
                                            ->imageResizeTargetHeight('1000')
                                            ->helperText(__('application_settings.fields.company_logo_light.helper'))
                                            ->columnSpanFull(),

                                        FileUpload::make('company_logo_dark')
                                            ->label(__('application_settings.fields.company_logo_dark.label'))
                                            ->image()
                                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml', 'image/webp'])
                                            ->maxSize(2048) // 2MB
                                            ->directory('company')
                                            ->disk('public')
                                            ->visibility('public')
                                            ->imageEditor()
                                            ->imageEditorAspectRatios([null, '1:1', '16:9'])
                                            ->imageResizeMode('contain')
                                            ->imageResizeTargetWidth('1000')
                                            ->imageResizeTargetHeight('1000')
                                            ->helperText(__('application_settings.fields.company_logo_dark.helper'))
                                            ->columnSpanFull(),

                                        TextInput::make('company_name')
                                            ->label(__('application_settings.fields.company_name.label'))
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText(__('application_settings.fields.company_name.helper'))
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make(__('application_settings.tabs.contact'))
                            ->schema([
                                Section::make(__('application_settings.sections.support_information.title'))
                                    ->description(__('application_settings.sections.support_information.description'))
                                    ->schema([
                                        TextInput::make('support_email')
                                            ->label(__('application_settings.fields.support_email.label'))
                                            ->email()
                                            ->required()
                                            ->helperText(__('application_settings.fields.support_email.helper'))
                                            ->columnSpanFull(),

                                        TextInput::make('support_url')
                                            ->label(__('application_settings.fields.support_url.label'))
                                            ->url()
                                            ->helperText(__('application_settings.fields.support_url.helper'))
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make(__('application_settings.tabs.legal'))
                            ->schema([
                                Section::make(__('application_settings.sections.legal_links.title'))
                                    ->description(__('application_settings.sections.legal_links.description'))
                                    ->schema([
                                        TextInput::make('privacy_policy_url')
                                            ->label(__('application_settings.fields.privacy_policy_url.label'))
                                            ->url()
                                            ->helperText(__('application_settings.fields.privacy_policy_url.helper'))
                                            ->columnSpanFull(),

                                        TextInput::make('terms_url')
                                            ->label(__('application_settings.fields.terms_url.label'))
                                            ->url()
                                            ->helperText(__('application_settings.fields.terms_url.helper'))
                                            ->columnSpanFull(),

                                        TextInput::make('data_protection_url')
                                            ->label(__('application_settings.fields.data_protection_url.label'))
                                            ->url()
                                            ->helperText(__('application_settings.fields.data_protection_url.helper'))
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('application_settings.actions.save'))
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        try {
            // Handle old logo deletion if changed
            $oldLogoLight = setting('company_logo_light');
            $newLogoLight = $data['company_logo_light'] ?? null;
            $oldLogoDark = setting('company_logo_dark');
            $newLogoDark = $data['company_logo_dark'] ?? null;

            if ($oldLogoLight && $oldLogoLight !== $newLogoLight && Storage::disk('public')->exists($oldLogoLight)) {
                Storage::disk('public')->delete($oldLogoLight);
            }

            if ($oldLogoDark && $oldLogoDark !== $newLogoDark && Storage::disk('public')->exists($oldLogoDark)) {
                Storage::disk('public')->delete($oldLogoDark);
            }

            // Save all settings
            foreach ($data as $key => $value) {
                setting([$key => $value]);
            }

            Notification::make()
                ->success()
                ->title(__('application_settings.notifications.saved.title'))
                ->body(__('application_settings.notifications.saved.body'))
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title(__('application_settings.notifications.error.title'))
                ->body(__('application_settings.notifications.error.body', ['message' => $e->getMessage()]))
                ->send();
        }
    }
}
