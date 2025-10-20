<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HostingResource\Pages;
use App\Models\{Host, Hosting, User};
use Filament\Forms\Components\{Grid, Select, TextInput, Toggle};
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\{BulkActionGroup, DeleteAction, DeleteBulkAction, EditAction, ForceDeleteAction, RestoreAction};
use Filament\Tables\Columns\{IconColumn, TextColumn};
use Filament\Tables\{Filters, Table};
use Illuminate\Database\Eloquent\{Builder, SoftDeletingScope};

class HostingResource extends Resource
{
    protected static ?string $model = Hosting::class;

    protected static ?string $slug = 'hostings';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)
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
                Filters\TrashedFilter::make(),
                Filters\SelectFilter::make('user_id')
                    ->label('Usuario')
                    ->relationship('user', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                    ->searchable()
                    ->preload(),
                Filters\SelectFilter::make('host_id')
                    ->label('Servidor')
                    ->relationship('host', 'fqdn')
                    ->searchable()
                    ->preload(),
                Filters\TernaryFilter::make('hosting_manual')
                    ->label('Manual'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHostings::route('/'),
            'create' => Pages\CreateHosting::route('/create'),
            'edit' => Pages\EditHosting::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['domain', 'username'];
    }
}
