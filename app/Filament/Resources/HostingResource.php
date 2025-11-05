<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HostingResource\Pages\{CreateHosting, EditHosting, ListHostings, ViewHosting};
use App\Models\{Host, Hosting, User};
use Filament\Forms\Components\{Select, TextInput, Toggle};
use Filament\Infolists;
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

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make(__('Hosting Information'))
                    ->schema([
                        Infolists\Components\TextEntry::make('user.first_name')
                            ->label(__('User'))
                            ->formatStateUsing(fn ($record) => $record->user?->name ?? '-')
                            ->url(fn ($record) => $record->user_id ? UserResource::getUrl('view', ['record' => $record->user_id]) : null)
                            ->icon('heroicon-o-user'),
                        Infolists\Components\TextEntry::make('host.fqdn')
                            ->label(__('Host'))
                            ->url(fn ($record) => HostResource::getUrl('view', ['record' => $record->host_id]))
                            ->icon('heroicon-o-server'),
                        Infolists\Components\TextEntry::make('domain')
                            ->label(__('Domain'))
                            ->copyable()
                            ->icon('heroicon-o-globe-alt'),
                        Infolists\Components\TextEntry::make('username')
                            ->label(__('Username'))
                            ->copyable(),
                        Infolists\Components\IconEntry::make('hosting_manual')
                            ->label(__('Manual'))
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('gray'),
                    ])->columns(2),
                \Filament\Schemas\Components\Section::make(__('Timestamps'))
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime()
                            ->since(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label(__('Updated At'))
                            ->dateTime()
                            ->since(),
                        Infolists\Components\TextEntry::make('deleted_at')
                            ->label(__('Deleted At'))
                            ->dateTime()
                            ->placeholder('-')
                            ->visible(fn ($record) => $record->deleted_at !== null),
                    ])->columns(3)->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.first_name')
                    ->label('Usuario')
                    ->formatStateUsing(fn ($record) => $record->user?->name ?? '-')
                    ->searchable(query: function ($query, $search) {
                        return $query->whereHas('user', function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->description(fn ($record) => $record->user?->email ?? '-')
                    ->toggleable(),

                TextColumn::make('host.fqdn')
                    ->label('Servidor')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->host->alias)
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
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
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
            'view' => ViewHosting::route('/{record}'),
            'edit' => EditHosting::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['domain', 'username'];
    }
}
