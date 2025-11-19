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

    protected static ?string $title = 'Application Settings';

    protected static ?int $navigationSort = 999;

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'company_logo' => setting('company_logo'),
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
                Tabs::make('Settings')
                    ->tabs([
                        Tabs\Tab::make('Branding')
                            ->schema([
                                Section::make('Company Branding')
                                    ->description('Customize your company appearance in the application')
                                    ->schema([
                                        FileUpload::make('company_logo')
                                            ->label('Company Logo')
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
                                            ->helperText('Upload company logo (PNG, JPG, SVG, WEBP). Max 2MB. Recommended: transparent background.')
                                            ->columnSpanFull(),

                                        TextInput::make('company_name')
                                            ->label('Company Name')
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText('Company name displayed in UI and emails')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Contact')
                            ->schema([
                                Section::make('Support Information')
                                    ->description('Configure support contact details')
                                    ->schema([
                                        TextInput::make('support_email')
                                            ->label('Support Email')
                                            ->email()
                                            ->required()
                                            ->helperText('Email address for customer support')
                                            ->columnSpanFull(),

                                        TextInput::make('support_url')
                                            ->label('Support URL')
                                            ->url()
                                            ->helperText('URL to your support/help desk system')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Legal')
                            ->schema([
                                Section::make('Legal Links')
                                    ->description('Configure legal compliance URLs')
                                    ->schema([
                                        TextInput::make('privacy_policy_url')
                                            ->label('Privacy Policy URL')
                                            ->url()
                                            ->helperText('Link to your privacy policy page')
                                            ->columnSpanFull(),

                                        TextInput::make('terms_url')
                                            ->label('Terms of Service URL')
                                            ->url()
                                            ->helperText('Link to your terms of service page')
                                            ->columnSpanFull(),

                                        TextInput::make('data_protection_url')
                                            ->label('Data Protection URL')
                                            ->url()
                                            ->helperText('Link to your data protection information')
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
                ->label('Save Settings')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        try {
            // Handle old logo deletion if changed
            $oldLogo = setting('company_logo');
            $newLogo = $data['company_logo'] ?? null;

            if ($oldLogo && $oldLogo !== $newLogo && Storage::disk('public')->exists($oldLogo)) {
                Storage::disk('public')->delete($oldLogo);
            }

            // Save all settings
            foreach ($data as $key => $value) {
                setting([$key => $value]);
            }

            Notification::make()
                ->success()
                ->title('Settings saved')
                ->body('Application settings have been updated successfully.')
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Failed to save settings: '.$e->getMessage())
                ->send();
        }
    }
}
