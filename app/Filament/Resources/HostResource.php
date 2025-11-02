<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HostResource\Pages\{CreateHost, EditHost, ListHosts};
use App\Filament\Resources\HostResource\{Pages, RelationManagers};
use App\Models\Host;
use Filament\Forms\Components\{TextInput, Textarea, Toggle};
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\{IconColumn, TextColumn};
use Filament\Tables\Filters\{SelectFilter, TernaryFilter, TrashedFilter};
use Filament\Tables\{Table};
use Illuminate\Database\Eloquent\{Builder, SoftDeletingScope};

class HostResource extends Resource
{
    protected static ?string $model = Host::class;

    protected static ?string $slug = 'hosts';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-server';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('whmcs_server_id')
                    ->label('WHMCS Server ID')
                    ->nullable(),
                TextInput::make('fqdn')
                    ->label('FQDN')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('alias')
                    ->required()
                    ->unique(ignoreRecord: true),
                Toggle::make('hosting_manual')
                    ->label('Manual')
                    ->default(false),

                \Filament\Schemas\Components\Fieldset::make('Acceso')
                    ->schema([
                        TextInput::make('ip')
                            ->label('IP')
                            ->required()
                            ->ipv4(),
                        TextInput::make('port_ssh')
                            ->label('Puerto SSH')
                            ->required()
                            ->default(22)
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535),
                        TextInput::make('panel')
                            ->default('directadmin')
                            ->required()
                            ->mutateDehydratedStateUsing(fn (?string $state): ?string => $state ? strtolower(trim($state)) : null),
                        TextInput::make('admin')
                            ->required(),
                    ])->columns(2),

                \Filament\Schemas\Components\Fieldset::make(__('hosts.ssh_keys.title'))
                    ->schema([
                        Textarea::make('hash')
                            ->label(__('hosts.ssh_keys.private_key'))
                            ->rows(5)
                            ->helperText(__('hosts.ssh_keys.private_key_help'))
                            ->placeholder('-----BEGIN OPENSSH PRIVATE KEY-----'),
                        Textarea::make('hash_public')
                            ->label(__('hosts.ssh_keys.public_key'))
                            ->rows(5)
                            ->helperText(__('hosts.ssh_keys.public_key_help'))
                            ->placeholder('ssh-ed25519 AAAA...'),
                    ])
                    ->columns(1),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fqdn')
                    ->label('FQDN')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('alias')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('ip')
                    ->label('IP')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('port_ssh')
                    ->label('Puerto SSH')
                    ->toggleable(),
                TextColumn::make('panel')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cpanel' => 'success',
                        'directadmin' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),
                TextColumn::make('hostings_count')
                    ->label('Hostings')
                    ->counts('hostings')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('accounts_count')
                    ->label('Accounts')
                    ->counts('accounts')
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('domains_count')
                    ->label('Domains')
                    ->counts('domains')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('hosting_manual')
                    ->label('Manual')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Eliminado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('panel')
                    ->options([
                        'cpanel' => 'cPanel',
                        'directadmin' => 'DirectAdmin',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Activo'),
                TernaryFilter::make('hosting_manual')
                    ->label('Manual'),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
                \Filament\Actions\RestoreAction::make(),
                \Filament\Actions\ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]));
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\HostingsRelationManager::class,
            RelationManagers\AccountsRelationManager::class,
            RelationManagers\DomainsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHosts::route('/'),
            'create' => CreateHost::route('/create'),
            'view' => Pages\ViewHost::route('/{record}'),
            'edit' => EditHost::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['fqdn', 'alias', 'ip'];
    }
}
