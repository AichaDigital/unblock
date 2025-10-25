<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\{
    RelationManagers\DelegatedUsersRelationManager,
    RelationManagers\HostingsRelationManager,
    RelationManagers\HostsRelationManager};
use App\Filament\Resources\UserResource\Pages\{CreateUser, EditUser, ListUsers, ViewUser};
use App\Models\User;
use Filament\Actions\{BulkActionGroup, DeleteAction, DeleteBulkAction, EditAction, ForceDeleteAction, ForceDeleteBulkAction, RestoreAction, RestoreBulkAction, ViewAction};
use Filament\Forms\Components\{Select, TextInput, Toggle};
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\{IconColumn, TextColumn};
use Filament\Tables\Filters\{SelectFilter, TrashedFilter};
use Filament\Tables\{Table};
use Illuminate\Database\Eloquent\{Builder, SoftDeletingScope};

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Información Personal')
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(4)
                            ->schema([
                                TextInput::make('first_name')
                                    ->label('Nombre')
                                    ->required(),
                                TextInput::make('last_name')
                                    ->label('Apellidos'),
                                TextInput::make('company_name')
                                    ->label('Empresa'),
                                TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true),
                            ]),
                    ])
                    ->columns(1),

                \Filament\Schemas\Components\Section::make('Acceso')
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(2)
                            ->schema([
                                TextInput::make('password')
                                    ->password()
                                    ->dehydrateStateUsing(fn (string $state): string => bcrypt($state))
                                    ->dehydrated(fn (?string $state): bool => filled($state))
                                    ->required(fn (string $operation): bool => $operation === 'create'),
                                TextInput::make('password_whmcs')
                                    ->password()
                                    ->label('Contraseña WHMCS'),
                            ]),
                    ]),

                \Filament\Schemas\Components\Grid::make(2)
                    ->schema([
                        \Filament\Schemas\Components\Section::make('Permisos y Estado')
                            ->schema([
                                Toggle::make('is_admin')
                                    ->label('Administrador'),
                                Select::make('parent_user_id')
                                    ->label('Usuario Principal (Responsable)')
                                    ->relationship('parentUser', 'email')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name.' ('.$record->email.')')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->helperText('Solo seleccionar si este usuario es un autorizado/delegado de otro usuario principal. Dejar vacío para usuarios principales.')
                                    ->options(function () {
                                        // Solo mostrar usuarios que NO tienen parent_user_id (usuarios principales)
                                        return User::whereNull('parent_user_id')
                                            ->pluck('email', 'id')
                                            ->mapWithKeys(function ($email, $id) {
                                                $user = User::find($id);

                                                return [$id => $user->name.' ('.$email.')'];
                                            });
                                    }),
                            ]),
                        \Filament\Schemas\Components\Section::make('Acceso')
                            ->schema([
                                TextInput::make('whmcs_client_id')
                                    ->label('ID Cliente WHMCS')
                                    ->nullable(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(['first_name', 'last_name'])
                    ->description(fn ($record) => $record->company_name),
                TextColumn::make('email')
                    ->searchable()
                    ->copyable(),
                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean(),
                TextColumn::make('parentUser.name')
                    ->label('Responsable')
                    ->searchable(['users.first_name', 'users.last_name'])
                    ->placeholder('Usuario Principal')
                    ->description(fn ($record) => $record->parentUser ? 'Autorizado por: '.$record->parentUser->email : 'Usuario independiente')
                    ->badge()
                    ->color(fn ($record) => $record->parent_user_id ? 'info' : 'success')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('created_at')
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
                SelectFilter::make('is_admin')
                    ->label('Tipo')
                    ->options([
                        '1' => 'Administrador',
                        '0' => 'Usuario Normal',
                    ]),
                SelectFilter::make('parent_user_id')
                    ->label('Tipo de Usuario')
                    ->options([
                        '' => 'Todos los usuarios',
                        'null' => 'Solo usuarios principales',
                        'not_null' => 'Solo usuarios autorizados',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === 'null') {
                            return $query->whereNull('parent_user_id');
                        } elseif ($data['value'] === 'not_null') {
                            return $query->whereNotNull('parent_user_id');
                        }

                        return $query;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]));
    }

    public static function getRelations(): array
    {
        return [
            HostingsRelationManager::class,
            HostsRelationManager::class,
            DelegatedUsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
            'view' => ViewUser::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'company_name', 'email'];
    }
}
