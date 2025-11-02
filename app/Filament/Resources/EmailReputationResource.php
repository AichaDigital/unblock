<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\EmailReputationResource\Pages\{ListEmailReputations, ViewEmailReputation};
use App\Models\EmailReputation;
use Filament\Actions\{Action, BulkActionGroup, EditAction, ViewAction};
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\{TextInput, Textarea};
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\{Filter, SelectFilter};
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmailReputationResource extends Resource
{
    protected static ?string $model = EmailReputation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    public static function getNavigationLabel(): string
    {
        return __('firewall.email_reputation.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('firewall.email_reputation.navigation_group');
    }

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('firewall.email_reputation.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('firewall.email_reputation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                \Filament\Schemas\Components\Section::make(__('firewall.email_reputation.email_information'))
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(2)
                            ->schema([
                                TextInput::make('email_hash')
                                    ->label(__('firewall.email_reputation.email_hash'))
                                    ->required()
                                    ->disabled()
                                    ->helperText(__('firewall.email_reputation.email_hash_helper')),
                                TextInput::make('email_domain')
                                    ->label(__('firewall.email_reputation.email_domain'))
                                    ->disabled(),
                            ]),
                    ]),

                \Filament\Schemas\Components\Section::make(__('firewall.email_reputation.reputation_statistics'))
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(3)
                            ->schema([
                                TextInput::make('reputation_score')
                                    ->label(__('firewall.email_reputation.reputation_score'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('/100')
                                    ->disabled(),
                                TextInput::make('total_requests')
                                    ->label(__('firewall.email_reputation.total_requests'))
                                    ->numeric()
                                    ->disabled(),
                                TextInput::make('verified_requests')
                                    ->label(__('firewall.email_reputation.verified_requests'))
                                    ->numeric()
                                    ->disabled(),
                            ]),
                        \Filament\Schemas\Components\Grid::make(2)
                            ->schema([
                                TextInput::make('failed_requests')
                                    ->label(__('firewall.email_reputation.failed_requests'))
                                    ->numeric()
                                    ->disabled(),
                                TextInput::make('last_seen_at')
                                    ->label(__('firewall.email_reputation.last_seen'))
                                    ->disabled(),
                            ]),
                    ]),

                \Filament\Schemas\Components\Section::make(__('firewall.email_reputation.notes'))
                    ->schema([
                        Textarea::make('notes')
                            ->label(__('firewall.email_reputation.admin_notes'))
                            ->rows(4)
                            ->helperText(__('firewall.email_reputation.admin_notes_helper')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email_hash')
                    ->label(__('firewall.email_reputation.email_hash'))
                    ->searchable()
                    ->formatStateUsing(fn ($state) => substr($state, 0, 16).'...')
                    ->copyable()
                    ->copyMessage(__('firewall.email_reputation.hash_copied'))
                    ->copyMessageDuration(1500),

                TextColumn::make('email_domain')
                    ->label(__('firewall.email_reputation.domain'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('reputation_score')
                    ->label(__('firewall.email_reputation.score'))
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->reputation_color)
                    ->formatStateUsing(fn ($state) => $state.'/100'),

                TextColumn::make('total_requests')
                    ->label(__('firewall.email_reputation.total'))
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('verified_requests')
                    ->label(__('firewall.email_reputation.verified'))
                    ->sortable()
                    ->alignCenter()
                    ->color('success'),

                TextColumn::make('failed_requests')
                    ->label(__('firewall.email_reputation.failed'))
                    ->sortable()
                    ->alignCenter()
                    ->color('danger'),

                TextColumn::make('verification_rate')
                    ->label(__('firewall.email_reputation.verification_rate'))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("(verified_requests / NULLIF(total_requests, 0)) {$direction}");
                    })
                    ->formatStateUsing(fn ($record) => number_format($record->verification_rate, 2).'%')
                    ->badge()
                    ->color(fn ($record) => $record->verification_rate >= 70 ? 'success' : ($record->verification_rate >= 40 ? 'warning' : 'danger'))
                    ->alignCenter(),

                TextColumn::make('last_seen_at')
                    ->label(__('firewall.email_reputation.last_seen'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->since(),

                TextColumn::make('created_at')
                    ->label(__('firewall.email_reputation.first_seen'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('reputation_score')
                    ->label(__('firewall.email_reputation.reputation'))
                    ->options([
                        'high' => __('firewall.email_reputation.high_reputation'),
                        'medium' => __('firewall.email_reputation.medium_reputation'),
                        'low' => __('firewall.email_reputation.low_reputation'),
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

                SelectFilter::make('email_domain')
                    ->label(__('firewall.email_reputation.email_domain'))
                    ->options(function (): array {
                        return EmailReputation::query()
                            ->select('email_domain')
                            ->distinct()
                            ->orderBy('email_domain')
                            ->pluck('email_domain', 'email_domain')
                            ->take(50)
                            ->toArray();
                    })
                    ->searchable(),

                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('created_from')
                            ->label(__('firewall.email_reputation.from')),
                        DatePicker::make('created_until')
                            ->label(__('firewall.email_reputation.until')),
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
                    ->label(__('firewall.email_reputation.incidents'))
                    ->icon('heroicon-o-exclamation-triangle')
                    ->url(fn ($record) => route('filament.admin.resources.abuse-incidents.index', [
                        'tableFilters[email_hash][value]' => $record->email_hash,
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
            'index' => ListEmailReputations::route('/'),
            'view' => ViewEmailReputation::route('/{record}'),
        ];
    }
}
