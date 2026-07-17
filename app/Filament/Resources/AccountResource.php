<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountResource\{Pages, RelationManagers\DomainsRelationManager};
use App\Models\{Account, User};
use BackedEnum;
use Filament\Actions\Action;
use Filament\{Actions, Forms, Infolists, Tables};
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\{Grid, Section};
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static ?string $slug = 'accounts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'username';

    #[\Override]
    public static function getNavigationLabel(): string
    {
        return __('accounts.Accounts');
    }

    #[\Override]
    public static function getNavigationGroup(): ?string
    {
        return __('hosts.Servers');
    }

    #[\Override]
    public static function getModelLabel(): string
    {
        return __('accounts.Account');
    }

    #[\Override]
    public static function getPluralModelLabel(): string
    {
        return __('accounts.Accounts');
    }

    #[\Override]
    public static function canCreate(): bool
    {
        return false; // Accounts are synced from servers
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('host_id')
                            ->label(__('accounts.Host'))
                            ->relationship('host', 'fqdn')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled()
                            ->helperText(__('accounts.Accounts are synced from servers and cannot be manually edited')),
                        Forms\Components\Select::make('user_id')
                            ->label(__('accounts.User'))
                            ->relationship('user', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (User $record) => $record->full_name)
                            ->nullable()
                            ->searchable(['first_name', 'last_name', 'email'])
                            ->preload(),
                        Forms\Components\TextInput::make('username')
                            ->label(__('accounts.Username'))
                            ->required()
                            ->maxLength(255)
                            ->disabled()
                            ->helperText(__('accounts.Account username in cPanel/DirectAdmin')),
                        Forms\Components\TextInput::make('domain')
                            ->label(__('accounts.Domain'))
                            ->required()
                            ->maxLength(255)
                            ->disabled(),
                        Forms\Components\TextInput::make('owner')
                            ->label(__('accounts.Owner'))
                            ->maxLength(255)
                            ->nullable()
                            ->disabled(),
                        Forms\Components\Placeholder::make('info')
                            ->label('')
                            ->content(__('accounts.Accounts are synced from servers and cannot be manually created or edited.')),
                    ]),
            ]);
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('accounts.Account Information'))
                    ->schema([
                        Infolists\Components\TextEntry::make('host.fqdn')
                            ->label(__('accounts.Host'))
                            ->url(fn (Account $record) => HostResource::getUrl('view', ['record' => $record->host_id]))
                            ->icon('heroicon-o-server'),
                        Infolists\Components\TextEntry::make('user.first_name')
                            ->label(__('accounts.User'))
                            ->placeholder('-')
                            ->formatStateUsing(fn (Account $record) => $record->user?->full_name ?? '-')
                            ->url(fn (Account $record) => $record->user_id ? UserResource::getUrl('view', ['record' => $record->user_id]) : null)
                            ->icon('heroicon-o-user')
                            ->default('-'),
                        Infolists\Components\TextEntry::make('username')
                            ->label(__('accounts.Username')),
                        Infolists\Components\TextEntry::make('domain')
                            ->label(__('accounts.Domain')),
                        Infolists\Components\TextEntry::make('owner')
                            ->label(__('accounts.Owner'))
                            ->placeholder('-'),
                    ])->columns(2),
                Section::make(__('accounts.Status'))
                    ->schema([
                        Infolists\Components\IconEntry::make('suspended_at')
                            ->label(__('accounts.Suspended'))
                            ->boolean()
                            ->trueIcon('heroicon-o-x-circle')
                            ->falseIcon('heroicon-o-check-circle')
                            ->trueColor('danger')
                            ->falseColor('success'),
                        Infolists\Components\IconEntry::make('deleted_at')
                            ->label(__('accounts.Deleted'))
                            ->boolean()
                            ->trueIcon('heroicon-o-trash')
                            ->falseIcon('heroicon-o-check-circle')
                            ->trueColor('danger')
                            ->falseColor('success'),
                        Infolists\Components\TextEntry::make('suspended_at')
                            ->label(__('accounts.Suspended At'))
                            ->dateTime()
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('deleted_at')
                            ->label(__('accounts.Deleted At'))
                            ->dateTime()
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('last_synced_at')
                            ->label(__('accounts.Last Synced'))
                            ->dateTime()
                            ->since()
                            ->placeholder('-'),
                    ])->columns(3),
                Section::make(__('accounts.Timestamps'))
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label(__('accounts.Created At'))
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label(__('accounts.Updated At'))
                            ->dateTime(),
                    ])->columns(2)->collapsible(),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('username')
            ->columns([
                Tables\Columns\TextColumn::make('host.fqdn')
                    ->label(__('accounts.Host'))
                    ->searchable()
                    ->sortable()
                    ->url(fn (Account $record) => HostResource::getUrl('view', ['record' => $record->host_id]))
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user.first_name')
                    ->label(__('accounts.User'))
                    ->formatStateUsing(fn (Account $record) => $record->user?->full_name ?? '-')
                    ->searchable(query: function ($query, $search) {
                        return $query->whereHas('user', function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->url(fn (Account $record) => $record->user_id ? UserResource::getUrl('view', ['record' => $record->user_id]) : null)
                    ->placeholder('-')
                    ->default('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('username')
                    ->label(__('accounts.Username'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('domain')
                    ->label(__('accounts.Domain'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('owner')
                    ->label(__('accounts.Owner'))
                    ->searchable()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('suspended_at')
                    ->label(__('accounts.Status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? __('messages.accounts.suspended') : __('messages.accounts.active'))
                    ->color(fn ($state) => $state ? 'danger' : 'success')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('deleted_at')
                    ->label(__('accounts.Deleted'))
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label(__('accounts.Last Synced'))
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('accounts.Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('host_id')
                    ->label(__('accounts.Host'))
                    ->relationship('host', 'fqdn')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label(__('accounts.User'))
                    ->relationship('user', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn (User $record) => $record->full_name)
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->preload(),
                Tables\Filters\TernaryFilter::make('suspended_at')
                    ->label(__('accounts.Suspended'))
                    ->nullable(),
                Tables\Filters\TernaryFilter::make('deleted_at')
                    ->label(__('accounts.Deleted'))
                    ->nullable(),
            ])
            ->recordActions([
                Action::make('toggle_suspension')
                    ->label(fn (Account $record) => $record->suspended_at ? __('messages.accounts.unsuspend') : __('messages.accounts.suspend'))
                    ->icon(fn (Account $record) => $record->suspended_at ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->color(fn (Account $record) => $record->suspended_at ? 'success' : 'danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Account $record) => $record->suspended_at ? __('messages.accounts.unsuspend_account') : __('messages.accounts.suspend_account'))
                    ->modalDescription(fn (Account $record) => $record->suspended_at
                        ? __('messages.accounts.unsuspend_confirmation')
                        : __('messages.accounts.suspend_confirmation'))
                    ->action(function (Account $record) {
                        if ($record->suspended_at) {
                            $record->update(['suspended_at' => null]);
                            Notification::make()
                                ->success()
                                ->title(__('messages.accounts.account_unsuspended'))
                                ->body(__('messages.accounts.account_unsuspended_success', ['username' => $record->username]))
                                ->send();
                        } else {
                            $record->update(['suspended_at' => now()]);
                            Notification::make()
                                ->success()
                                ->title(__('messages.accounts.account_suspended'))
                                ->body(__('messages.accounts.account_suspended_success', ['username' => $record->username]))
                                ->send();
                        }
                    }),
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->headerActions([
                // No create - accounts are synced from servers
            ])
            ->defaultSort('last_synced_at', 'desc');
    }

    #[\Override]
    public static function getRelations(): array
    {
        return [
            DomainsRelationManager::class,
        ];
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'view' => Pages\ViewAccount::route('/{record}'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }
}
