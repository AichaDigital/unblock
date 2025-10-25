<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HostingResource\Pages\{CreateHosting, EditHosting, ListHostings};
use App\Models\{Host, Hosting, User};
use Filament\Forms\Components\{Select, TextInput, Toggle};
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\{IconColumn, TextColumn};
use Filament\Tables\Filters\{SelectFilter, TernaryFilter, TrashedFilter};
use Filament\Tables\{Table};
use Illuminate\Database\Eloquent\{Builder, SoftDeletingScope};

class HostingResource extends Resource
{
    protected static ?string $model = Hosting::class;

    protected static ?string $slug = 'hostings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Grid::make(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('Usuario')
                            ->options(User::query()->get()->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('host_id')
                            ->label('Servidor')
                            ->options(Host::query()->pluck('fqdn', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),

                        TextInput::make('domain')
                            ->label('Dominio')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('username')
                            ->label('Usuario cPanel/DA')
                            ->required()
                            ->maxLength(255),

                        Toggle::make('hosting_manual')
                            ->label('Manual')
                            ->default(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable(['users.first_name', 'users.last_name'])
                    ->sortable()
                    ->description(fn ($record) => $record->user->email),

                TextColumn::make('host.fqdn')
                    ->label('Servidor')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->host->alias),

                TextColumn::make('domain')
                    ->label('Dominio')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => "Usuario: {$record->username}"),

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
                SelectFilter::make('user_id')
                    ->label('Usuario')
                    ->relationship('user', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('host_id')
                    ->label('Servidor')
                    ->relationship('host', 'fqdn')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('hosting_manual')
                    ->label('Manual'),
            ])
            ->recordActions([
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

    public static function getPages(): array
    {
        return [
            'index' => ListHostings::route('/'),
            'create' => CreateHosting::route('/create'),
            'edit' => EditHosting::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['domain', 'username'];
    }
}
