<?php

namespace App\Filament\Resources\HostResource\RelationManagers;

use Filament\{Actions, Forms, Tables};
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'accounts';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('domain')
                    ->required()
                    ->maxLength(255)
                    ->disabled(),
                Forms\Components\TextInput::make('username')
                    ->required()
                    ->maxLength(255)
                    ->disabled(),
                Forms\Components\Placeholder::make('info')
                    ->content('Accounts are synced from servers and cannot be manually edited.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain')
            ->columns([
                Tables\Columns\TextColumn::make('username')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('owner')
                    ->searchable()
                    ->sortable()
                    ->default('-'),
                Tables\Columns\IconColumn::make('suspended_at')
                    ->label('Suspended')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('deleted_at')
                    ->label('Deleted')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label('Last Sync')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('suspended_at')
                    ->label('Suspended')
                    ->nullable(),
                Tables\Filters\TernaryFilter::make('deleted_at')
                    ->label('Deleted')
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
            ->defaultSort('last_synced_at', 'desc');
    }
}
