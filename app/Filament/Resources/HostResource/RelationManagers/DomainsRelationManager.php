<?php

namespace App\Filament\Resources\HostResource\RelationManagers;

use Filament\{Actions, Forms, Tables};
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('domain_name')
                    ->required()
                    ->maxLength(255)
                    ->disabled(),
                Forms\Components\TextInput::make('type')
                    ->required()
                    ->disabled(),
                Forms\Components\Placeholder::make('info')
                    ->content('Domains are synced from servers and cannot be manually edited.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain_name')
            ->columns([
                Tables\Columns\TextColumn::make('domain_name')
                    ->label('Domain')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'primary' => 'success',
                        'addon' => 'info',
                        'parked' => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('account.username')
                    ->label('Account')
                    ->searchable()
                    ->sortable()
                    ->default('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('account.domain')
                    ->label('Account Domain')
                    ->searchable()
                    ->sortable()
                    ->default('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('account.suspended_at')
                    ->label('Account Suspended')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('account.deleted_at')
                    ->label('Account Deleted')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'primary' => 'Primary',
                        'addon' => 'Addon',
                        'parked' => 'Parked',
                    ]),
                Tables\Filters\TernaryFilter::make('account.suspended_at')
                    ->label('Account Suspended')
                    ->nullable(),
                Tables\Filters\TernaryFilter::make('account.deleted_at')
                    ->label('Account Deleted')
                    ->nullable(),
            ])
            ->headerActions([
                // No create/associate actions - data is synced from servers
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                // No edit/delete actions - data is synced from servers
            ])
            ->toolbarActions([
                // No bulk actions - data is synced from servers
            ])
            ->defaultSort('domain_name', 'asc');
    }
}
