<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\Host;
use Filament\Forms\Components\{Grid, Select, TextInput};
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HostingsRelationManager extends RelationManager
{
    protected static string $relationship = 'hostings';

    protected static ?string $title = 'Hostings';

    protected static ?string $modelLabel = 'Hosting';

    protected static ?string $pluralModelLabel = 'Hostings';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)
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
                Tables\Columns\TextColumn::make('host.fqdn')
                    ->label('Servidor')
                    ->description(fn ($record) => "ID: {$record->host_id}")
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('domain')
                    ->label('Dominio')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => "Usuario: {$record->username}"),
                Tables\Columns\IconColumn::make('hosting_manual')
                    ->label('Manual')
                    ->boolean()
                    ->tooltip('Indica si el alojamiento fue creado manualmente'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultGroup('host.fqdn')
            ->groups([
                'host.fqdn' => Tables\Grouping\Group::make('Servidor')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('host_id')
                    ->label('Servidor')
                    ->relationship('host', 'fqdn')
                    ->multiple()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('hosting_manual')
                    ->label('Manual')
                    ->boolean()
                    ->trueLabel('Manual')
                    ->falseLabel('Automático'),
            ])
            ->headerActions([
                // Solo permitir crear si el usuario es reseller o admin
                Tables\Actions\CreateAction::make()
                    ->label('Crear Alojamiento')
                    ->visible(fn ($livewire) => $livewire->getOwnerRecord()->is_admin
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver Detalles'),
                // Solo permitir editar/eliminar si el usuario es reseller o admin
                Tables\Actions\EditAction::make()
                    ->visible(fn ($livewire) => $livewire->getOwnerRecord()->is_admin
                    ),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($livewire) => $livewire->getOwnerRecord()->is_admin
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn ($livewire) => $livewire->getOwnerRecord()->is_admin
                        ),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with('host'));
    }
}
