<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\Host;
use Filament\Actions\{BulkActionGroup, CreateAction, DeleteAction, DeleteBulkAction, EditAction, ViewAction};
use Filament\Forms\Components\{Select, TextInput};
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\{IconColumn, TextColumn};
use Filament\Tables\Filters\{SelectFilter, TernaryFilter};
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HostingsRelationManager extends RelationManager
{
    protected static string $relationship = 'hostings';

    protected static ?string $title = 'Hostings';

    protected static ?string $modelLabel = 'Hosting';

    protected static ?string $pluralModelLabel = 'Hostings';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Grid::make(2)
                    ->schema([
                        Select::make('host_id')
                            ->label('Servidor')
                            ->options(Host::query()->pluck('fqdn', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Servidor donde está alojado el dominio'),
                        TextInput::make('domain')
                            ->label('Dominio')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Nombre del dominio completo (ejemplo.com)'),
                        TextInput::make('username')
                            ->label('Usuario cPanel/DA')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Usuario en el panel de control del servidor'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain')
            ->columns([
                TextColumn::make('host.fqdn')
                    ->label('Servidor')
                    ->description(fn ($record) => "ID: {$record->host_id}")
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('domain')
                    ->label('Dominio')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => "Usuario: {$record->username}")
                    ->toggleable(),
                IconColumn::make('hosting_manual')
                    ->label('Manual')
                    ->boolean()
                    ->tooltip('Indica si el alojamiento fue creado manualmente')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultGroup('host.fqdn')
            ->groups([
                'host.fqdn' => Group::make('Servidor')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false),
            ])
            ->filters([
                SelectFilter::make('host_id')
                    ->label('Servidor')
                    ->relationship('host', 'fqdn')
                    ->multiple()
                    ->preload(),
                TernaryFilter::make('hosting_manual')
                    ->label('Manual')
                    ->boolean()
                    ->trueLabel('Manual')
                    ->falseLabel('Automático'),
            ])
            ->headerActions([
                // Solo permitir crear si el usuario es reseller o admin
                CreateAction::make()
                    ->label('Crear Alojamiento')
                    ->visible(fn ($livewire) => $livewire->getOwnerRecord()->is_admin
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver Detalles'),
                // Solo permitir editar/eliminar si el usuario es reseller o admin
                EditAction::make()
                    ->visible(fn ($livewire) => $livewire->getOwnerRecord()->is_admin
                    ),
                DeleteAction::make()
                    ->visible(fn ($livewire) => $livewire->getOwnerRecord()->is_admin
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn ($livewire) => $livewire->getOwnerRecord()->is_admin
                        ),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with('host'));
    }
}
