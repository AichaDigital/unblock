<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Enums\PanelType;
use App\Models\Host;
use Filament\Actions\{AttachAction, BulkActionGroup, DetachAction, DetachBulkAction, EditAction};
use Filament\Forms\Components\{Select, Toggle};
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\{IconColumn, TextColumn};
use Filament\Tables\Filters\{SelectFilter, TernaryFilter};
use Filament\Tables\Table;

class HostsRelationManager extends RelationManager
{
    protected static string $relationship = 'hosts';

    protected static ?string $title = 'Acceso a Servidores';

    protected static ?string $modelLabel = 'Servidor';

    protected static ?string $pluralModelLabel = 'Servidores';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Grid::make(2)
                    ->schema([
                        Select::make('recordId')
                            ->label('Servidor')
                            ->options(function () {
                                // Obtener hosts que NO están ya asignados a este usuario
                                $assignedHostIds = $this->getOwnerRecord()->hosts()->pluck('hosts.id')->toArray();

                                return Host::whereNotIn('id', $assignedHostIds)
                                    ->get()
                                    ->mapWithKeys(fn ($host) => [
                                        $host->id => "{$host->fqdn} ({$host->ip}) - {$host->panel->getLabel()}",
                                    ]);
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('fqdn')
            ->columns([
                TextColumn::make('fqdn')
                    ->label('FQDN')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('alias')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('ip')
                    ->label('IP')
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('pivot.is_active')
                    ->label('Activo')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('panel')
                    ->label('Panel')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Asignado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('panel')
                    ->options(PanelType::class),
                TernaryFilter::make('pivot.is_active')
                    ->label('Activo'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Asignar Servidor')
                    ->form(fn (Schema $schema): Schema => $this->form($schema))
                    ->attachAnother(false)
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(function ($query) {
                        // Solo mostrar hosts que NO están ya asignados a este usuario
                        $assignedHostIds = $this->getOwnerRecord()->hosts()->pluck('hosts.id')->toArray();

                        return $query->whereNotIn('id', $assignedHostIds);
                    })
                    ->recordSelectSearchColumns(['fqdn', 'alias', 'ip'])
                    ->recordTitle(fn ($record) => "{$record->fqdn} ({$record->ip}) - {$record->panel->getLabel()}"),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar Permisos')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Activo'),
                    ])
                    ->using(function (array $data, $record): void {
                        $this->getOwnerRecord()->hosts()->updateExistingPivot($record->id, [
                            'is_active' => $data['is_active'],
                        ]);
                    }),
                DetachAction::make()
                    ->label('Revocar Acceso'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label('Revocar Acceso'),
                ]),
            ])
            ->emptyStateActions([
                AttachAction::make()
                    ->label('Asignar Primer Servidor')
                    ->form(fn (Schema $schema): Schema => $this->form($schema))
                    ->attachAnother(false)
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(function ($query) {
                        $assignedHostIds = $this->getOwnerRecord()->hosts()->pluck('hosts.id')->toArray();

                        return $query->whereNotIn('id', $assignedHostIds);
                    })
                    ->recordSelectSearchColumns(['fqdn', 'alias', 'ip'])
                    ->recordTitle(fn ($record) => "{$record->fqdn} ({$record->ip}) - {$record->panel->getLabel()}"),
            ]);
    }
}
