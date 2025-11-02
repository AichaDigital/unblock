<?php

namespace App\Filament\Resources\AccountResource\RelationManagers;

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
                    ->label(__('Domain Name'))
                    ->required()
                    ->maxLength(255)
                    ->disabled(),
                Forms\Components\Select::make('type')
                    ->label(__('Type'))
                    ->options([
                        'primary' => __('Primary'),
                        'addon' => __('Addon'),
                        'subdomain' => __('Subdomain'),
                        'alias' => __('Alias'),
                    ])
                    ->required()
                    ->disabled(),
                Forms\Components\Placeholder::make('info')
                    ->content(__('Domains are synced from servers and cannot be manually edited.')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain_name')
            ->columns([
                Tables\Columns\TextColumn::make('domain_name')
                    ->label(__('Domain Name'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'primary' => 'success',
                        'addon' => 'info',
                        'subdomain' => 'warning',
                        'alias' => 'gray',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options([
                        'primary' => __('Primary'),
                        'addon' => __('Addon'),
                        'subdomain' => __('Subdomain'),
                        'alias' => __('Alias'),
                    ]),
            ])
            ->headerActions([
                // No create/associate actions - domains are synced from servers
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                // No edit/delete actions - domains are synced from servers
            ])
            ->toolbarActions([
                // No bulk actions - domains are synced from servers
            ])
            ->defaultSort('type', 'asc');
    }
}

