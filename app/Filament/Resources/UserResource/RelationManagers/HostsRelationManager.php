<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\Host;
use Filament\Forms\Components\{Grid, Select, Toggle};
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class HostsRelationManager extends RelationManager
{
    protected static string $relationship = 'hosts';

    protected static ?string $title = 'Acceso a Servidores';

    protected static ?string $modelLabel = 'Servidor';

    protected static ?string $pluralModelLabel = 'Servidores';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)
                    ->schema([
                        Select::make('recordId')
                            ->label('Servidor')
                            ->options(function () {
                                // Obtener hosts que NO están ya asignados a este usuario
                                $assignedHostIds = $this->getOwnerRecord()->hosts()->pluck('hosts.id')->toArray();

                                return Host::whereNotIn('id', $assignedHostIds)
                                    ->get()
                                    ->mapWithKeys(fn ($host) => [
                                        $host->id => "{$host->fqdn} ({$host->ip}) - {$host->panel}",
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
                Tables\Columns\TextColumn::make('fqdn')
                    ->label('FQDN')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('alias')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ip')
                    ->label('IP')
                    ->searchable(),
                Tables\Columns\IconColumn::make('pivot.is_active')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('panel')
                    ->label('Panel')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cpanel' => 'success',
                        'directadmin' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Asignado')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('panel')
                    ->options([
                        'cpanel' => 'cPanel',
                        'directadmin' => 'DirectAdmin',
                    ]),
                Tables\Filters\TernaryFilter::make('pivot.is_active')
                    ->label('Activo'),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Asignar Servidor')
                    ->form(fn (Form $form): Form => $this->form($form))
                    ->attachAnother(false)
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(function ($query) {
                        // Solo mostrar hosts que NO están ya asignados a este usuario
                        $assignedHostIds = $this->getOwnerRecord()->hosts()->pluck('hosts.id')->toArray();

                        return $query->whereNotIn('id', $assignedHostIds);
                    })
                    ->recordSelectSearchColumns(['fqdn', 'alias', 'ip'])
                    ->recordTitle(fn ($record) => "{$record->fqdn} ({$record->ip}) - {$record->panel}"),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar Permisos')
                    ->form([
                        Toggle::make('is_active')
                            ->label('Activo'),
                    ])
                    ->using(function (array $data, $record): void {
                        $this->getOwnerRecord()->hosts()->updateExistingPivot($record->id, [
                            'is_active' => $data['is_active'],
                        ]);
                    }),
                Tables\Actions\DetachAction::make()
                    ->label('Revocar Acceso'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->label('Revocar Acceso'),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\AttachAction::make()
                    ->label('Asignar Primer Servidor')
                    ->form(fn (Form $form): Form => $this->form($form))
                    ->attachAnother(false)
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(function ($query) {
                        $assignedHostIds = $this->getOwnerRecord()->hosts()->pluck('hosts.id')->toArray();

                        return $query->whereNotIn('id', $assignedHostIds);
                    })
                    ->recordSelectSearchColumns(['fqdn', 'alias', 'ip'])
                    ->recordTitle(fn ($record) => "{$record->fqdn} ({$record->ip}) - {$record->panel}"),
            ]);
    }
}
