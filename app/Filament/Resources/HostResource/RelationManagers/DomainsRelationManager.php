<?php

namespace App\Filament\Resources\HostResource\RelationManagers;

use Filament\{Actions, Forms, Tables};
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    #[\Override]
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
                    ->content(__('domains.Domains are synced from servers and cannot be manually edited.')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain_name')
            ->columns([
                Tables\Columns\TextColumn::make('domain_name')
                    ->label(__('domains.Domain'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('domains.Type'))
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
                    ->label(__('domains.Account'))
                    ->searchable()
                    ->sortable()
                    ->default('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('account.domain')
                    ->label(__('domains.Account Domain'))
                    ->searchable()
                    ->sortable()
                    ->default('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('account.suspended_at')
                    ->label(__('domains.Account Suspended'))
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('account.deleted_at')
                    ->label(__('domains.Account Deleted'))
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('domains.Created'))
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'primary' => __('domains.Primary'),
                        'addon' => __('domains.Addon'),
                        'parked' => __('domains.Parked'),
                    ]),
                Tables\Filters\TernaryFilter::make('account.suspended_at')
                    ->label(__('domains.Account Suspended'))
                    ->nullable(),
                Tables\Filters\TernaryFilter::make('account.deleted_at')
                    ->label(__('domains.Account Deleted'))
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
