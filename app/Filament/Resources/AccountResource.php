<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountResource\Pages;
use App\Filament\Resources\AccountResource\RelationManagers\DomainsRelationManager;
use App\Models\{Account, User};
use BackedEnum;
use Filament\{Actions, Forms, Infolists, Tables};
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static ?string $slug = 'accounts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'username';

    public static function getNavigationLabel(): string
    {
        return __('Accounts');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Servers');
    }

    public static function getModelLabel(): string
    {
        return __('Account');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Accounts');
    }

    public static function canCreate(): bool
    {
        return false; // Accounts are synced from servers
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('host_id')
                            ->label(__('Host'))
                            ->relationship('host', 'fqdn')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled()
                            ->helperText(__('Accounts are synced from servers and cannot be manually edited')),
                        Forms\Components\Select::make('user_id')
                            ->label(__('User'))
                            ->relationship('user', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (User $record) => $record->getFullNameAttribute())
                            ->nullable()
                            ->searchable(['first_name', 'last_name', 'email'])
                            ->preload(),
                        Forms\Components\TextInput::make('username')
                            ->label(__('Username'))
                            ->required()
                            ->maxLength(255)
                            ->disabled()
                            ->helperText(__('Account username in cPanel/DirectAdmin')),
                        Forms\Components\TextInput::make('domain')
                            ->label(__('Domain'))
                            ->required()
                            ->maxLength(255)
                            ->disabled(),
                        Forms\Components\TextInput::make('owner')
                            ->label(__('Owner'))
                            ->maxLength(255)
                            ->nullable()
                            ->disabled(),
                        Forms\Components\Placeholder::make('info')
                            ->label('')
                            ->content(__('Accounts are synced from servers and cannot be manually created or edited.')),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make(__('Account Information'))
                    ->schema([
                        Infolists\Components\TextEntry::make('host.fqdn')
                            ->label(__('Host'))
                            ->url(fn (Account $record) => HostResource::getUrl('view', ['record' => $record->host_id]))
                            ->icon('heroicon-o-server'),
                        Infolists\Components\TextEntry::make('user.first_name')
                            ->label(__('User'))
                            ->placeholder('-')
                            ->formatStateUsing(fn (Account $record) => $record->user?->getFullNameAttribute() ?? '-')
                            ->url(fn (Account $record) => $record->user_id ? UserResource::getUrl('view', ['record' => $record->user_id]) : null)
                            ->icon('heroicon-o-user')
                            ->default('-'),
                        Infolists\Components\TextEntry::make('username')
                            ->label(__('Username')),
                        Infolists\Components\TextEntry::make('domain')
                            ->label(__('Domain')),
                        Infolists\Components\TextEntry::make('owner')
                            ->label(__('Owner'))
                            ->placeholder('-'),
                    ])->columns(2),
                \Filament\Schemas\Components\Section::make(__('Status'))
                    ->schema([
                        Infolists\Components\IconEntry::make('suspended_at')
                            ->label(__('Suspended'))
                            ->boolean()
                            ->trueIcon('heroicon-o-x-circle')
                            ->falseIcon('heroicon-o-check-circle')
                            ->trueColor('danger')
                            ->falseColor('success'),
                        Infolists\Components\IconEntry::make('deleted_at')
                            ->label(__('Deleted'))
                            ->boolean()
                            ->trueIcon('heroicon-o-trash')
                            ->falseIcon('heroicon-o-check-circle')
                            ->trueColor('danger')
                            ->falseColor('success'),
                        Infolists\Components\TextEntry::make('suspended_at')
                            ->label(__('Suspended At'))
                            ->dateTime()
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('deleted_at')
                            ->label(__('Deleted At'))
                            ->dateTime()
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('last_synced_at')
                            ->label(__('Last Synced'))
                            ->dateTime()
                            ->since()
                            ->placeholder('-'),
                    ])->columns(3),
                \Filament\Schemas\Components\Section::make(__('Timestamps'))
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label(__('Updated At'))
                            ->dateTime(),
                    ])->columns(2)->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('username')
            ->columns([
                Tables\Columns\TextColumn::make('host.fqdn')
                    ->label(__('Host'))
                    ->searchable()
                    ->sortable()
                    ->url(fn (Account $record) => HostResource::getUrl('view', ['record' => $record->host_id]))
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user.first_name')
                    ->label(__('User'))
                    ->formatStateUsing(fn (Account $record) => $record->user?->getFullNameAttribute() ?? '-')
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
                    ->label(__('Username'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('domain')
                    ->label(__('Domain'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('owner')
                    ->label(__('Owner'))
                    ->searchable()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('suspended_at')
                    ->label(__('Suspended'))
                    ->boolean()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('deleted_at')
                    ->label(__('Deleted'))
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label(__('Last Synced'))
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('host_id')
                    ->label(__('Host'))
                    ->relationship('host', 'fqdn')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label(__('User'))
                    ->relationship('user', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn (User $record) => $record->getFullNameAttribute())
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->preload(),
                Tables\Filters\TernaryFilter::make('suspended_at')
                    ->label(__('Suspended'))
                    ->nullable(),
                Tables\Filters\TernaryFilter::make('deleted_at')
                    ->label(__('Deleted'))
                    ->nullable(),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                // No edit/delete - accounts are synced from servers
            ])
            ->headerActions([
                // No create - accounts are synced from servers
            ])
            ->defaultSort('last_synced_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            DomainsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'view' => Pages\ViewAccount::route('/{record}'),
            // No create/edit pages - accounts are synced from servers
        ];
    }
}
