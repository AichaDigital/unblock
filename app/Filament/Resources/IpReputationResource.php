<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\IpReputationResource\Pages\{ListIpReputations, ViewIpReputation};
use App\Models\IpReputation;
use Filament\Actions\{Action, BulkActionGroup, EditAction, ViewAction};
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\{TextInput, Textarea};
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\{Filter, SelectFilter};
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IpReputationResource extends Resource
{
    protected static ?string $model = IpReputation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    public static function getNavigationLabel(): string
    {
        return __('firewall.ip_reputation.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('firewall.ip_reputation.navigation_group');
    }

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('firewall.ip_reputation.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('firewall.ip_reputation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make(__('firewall.ip_reputation.ip_information'))
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(2)
                            ->schema([
                                TextInput::make('ip')
                                    ->label(__('firewall.ip_reputation.ip_address'))
                                    ->required()
                                    ->disabled(),
                                TextInput::make('subnet')
                                    ->label(__('firewall.ip_reputation.subnet'))
                                    ->disabled(),
                            ]),
                    ]),

                \Filament\Schemas\Components\Section::make(__('firewall.ip_reputation.reputation_statistics'))
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(3)
                            ->schema([
                                TextInput::make('reputation_score')
                                    ->label(__('firewall.ip_reputation.reputation_score'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('/100')
                                    ->disabled(),
                                TextInput::make('total_requests')
                                    ->label(__('firewall.ip_reputation.total_requests'))
                                    ->numeric()
                                    ->disabled(),
                                TextInput::make('failed_requests')
                                    ->label(__('firewall.ip_reputation.failed_requests'))
                                    ->numeric()
                                    ->disabled(),
                            ]),
                        \Filament\Schemas\Components\Grid::make(2)
                            ->schema([
                                TextInput::make('blocked_count')
                                    ->label(__('firewall.ip_reputation.blocked_count'))
                                    ->numeric()
                                    ->disabled(),
                                TextInput::make('last_seen_at')
                                    ->label(__('firewall.ip_reputation.last_seen'))
                                    ->disabled(),
                            ]),
                    ]),

                \Filament\Schemas\Components\Section::make('Geographic Information')
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(3)
                            ->schema([
                                TextInput::make('country_name')
                                    ->label('Country')
                                    ->disabled(),
                                TextInput::make('city')
                                    ->label('City')
                                    ->disabled(),
                                TextInput::make('timezone')
                                    ->label('Timezone')
                                    ->disabled(),
                            ]),
                        \Filament\Schemas\Components\Grid::make(2)
                            ->schema([
                                TextInput::make('latitude')
                                    ->label('Latitude')
                                    ->disabled(),
                                TextInput::make('longitude')
                                    ->label('Longitude')
                                    ->disabled(),
                            ]),
                    ])
                    ->collapsed()
                    ->visible(fn ($record) => $record && $record->country_code !== null),

                \Filament\Schemas\Components\Section::make(__('firewall.ip_reputation.notes'))
                    ->schema([
                        Textarea::make('notes')
                            ->label(__('firewall.ip_reputation.admin_notes'))
                            ->rows(4)
                            ->helperText(__('firewall.ip_reputation.admin_notes_helper')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ip')
                    ->label(__('firewall.ip_reputation.ip_address'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('subnet')
                    ->label(__('firewall.ip_reputation.subnet'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('reputation_score')
                    ->label(__('firewall.ip_reputation.score'))
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->reputation_color)
                    ->formatStateUsing(fn ($state) => $state.'/100'),

                TextColumn::make('total_requests')
                    ->label(__('firewall.ip_reputation.total'))
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('failed_requests')
                    ->label(__('firewall.ip_reputation.failed'))
                    ->sortable()
                    ->alignCenter()
                    ->color('danger'),

                TextColumn::make('blocked_count')
                    ->label(__('firewall.ip_reputation.blocked'))
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('success_rate')
                    ->label(__('firewall.ip_reputation.success_rate'))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("(1 - (failed_requests / NULLIF(total_requests, 0))) {$direction}");
                    })
                    ->formatStateUsing(fn ($record) => number_format($record->success_rate, 2).'%')
                    ->badge()
                    ->color(fn ($record) => $record->success_rate >= 80 ? 'success' : ($record->success_rate >= 50 ? 'warning' : 'danger'))
                    ->alignCenter(),

                TextColumn::make('country_name')
                    ->label('Country')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->icon(fn ($record) => $record->country_code ? 'heroicon-o-globe-alt' : null)
                    ->description(fn ($record) => $record->city),

                TextColumn::make('last_seen_at')
                    ->label(__('firewall.ip_reputation.last_seen'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->since(),

                TextColumn::make('created_at')
                    ->label(__('firewall.ip_reputation.first_seen'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('reputation_score')
                    ->label(__('firewall.ip_reputation.reputation'))
                    ->options([
                        'high' => __('firewall.ip_reputation.high_reputation'),
                        'medium' => __('firewall.ip_reputation.medium_reputation'),
                        'low' => __('firewall.ip_reputation.low_reputation'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! isset($data['value'])) {
                            return $query;
                        }

                        return match ($data['value']) {
                            'high' => $query->where('reputation_score', '>=', 80),
                            'medium' => $query->whereBetween('reputation_score', [50, 79]),
                            'low' => $query->where('reputation_score', '<', 50),
                            default => $query,
                        };
                    }),

                SelectFilter::make('country_code')
                    ->label('Country')
                    ->options(fn () => IpReputation::whereNotNull('country_code')
                        ->distinct()
                        ->pluck('country_name', 'country_code')
                        ->toArray()
                    )
                    ->searchable(),

                Filter::make('subnet')
                    ->schema([
                        TextInput::make('subnet')
                            ->label(__('firewall.ip_reputation.subnet_search'))
                            ->placeholder('e.g., 192.168.1'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['subnet'],
                            fn (Builder $query, $subnet): Builder => $query->where('subnet', 'like', "%{$subnet}%")
                        );
                    }),

                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('created_from')
                            ->label(__('firewall.ip_reputation.from')),
                        DatePicker::make('created_until')
                            ->label(__('firewall.ip_reputation.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date)
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn () => false), // Disable edit for now (read-only except notes)
                Action::make('viewIncidents')
                    ->label(__('firewall.ip_reputation.incidents'))
                    ->icon('heroicon-o-exclamation-triangle')
                    ->url(fn ($record) => route('filament.admin.resources.abuse-incidents.index', [
                        'tableFilters[ip_address][value]' => $record->ip,
                    ]))
                    ->color('warning'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Bulk actions can be added here if needed
                ]),
            ])
            ->defaultSort('last_seen_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIpReputations::route('/'),
            'view' => ViewIpReputation::route('/{record}'),
        ];
    }
}
