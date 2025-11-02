<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AbuseIncidentResource\Pages\{ListAbuseIncidents, ViewAbuseIncident};
use App\Models\AbuseIncident;
use Filament\Actions\{Action, BulkAction, BulkActionGroup, ViewAction};
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\{DateTimePicker, Select, TextInput, Textarea};
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\{IconColumn, TextColumn};
use Filament\Tables\Filters\{Filter, SelectFilter, TernaryFilter};
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AbuseIncidentResource extends Resource
{
    protected static ?string $model = AbuseIncident::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    public static function getNavigationLabel(): string
    {
        return __('firewall.abuse_incidents.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('firewall.abuse_incidents.navigation_group');
    }

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('firewall.abuse_incidents.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('firewall.abuse_incidents.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                \Filament\Schemas\Components\Section::make(__('firewall.abuse_incidents.incident_information'))
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(3)
                            ->schema([
                                Select::make('incident_type')
                                    ->label(__('firewall.abuse_incidents.incident_type'))
                                    ->options([
                                        'rate_limit_exceeded' => __('firewall.abuse_incidents.types.rate_limit_exceeded'),
                                        'ip_spoofing_attempt' => __('firewall.abuse_incidents.types.ip_spoofing_attempt'),
                                        'otp_bruteforce' => __('firewall.abuse_incidents.types.otp_bruteforce'),
                                        'honeypot_triggered' => __('firewall.abuse_incidents.types.honeypot_triggered'),
                                        'invalid_otp_attempts' => __('firewall.abuse_incidents.types.invalid_otp_attempts'),
                                        'ip_mismatch' => __('firewall.abuse_incidents.types.ip_mismatch'),
                                        'suspicious_pattern' => __('firewall.abuse_incidents.types.suspicious_pattern'),
                                        'other' => __('firewall.abuse_incidents.types.other'),
                                    ])
                                    ->required()
                                    ->disabled(),
                                Select::make('severity')
                                    ->label(__('firewall.abuse_incidents.severity'))
                                    ->options([
                                        'low' => __('firewall.abuse_incidents.severity_levels.low'),
                                        'medium' => __('firewall.abuse_incidents.severity_levels.medium'),
                                        'high' => __('firewall.abuse_incidents.severity_levels.high'),
                                        'critical' => __('firewall.abuse_incidents.severity_levels.critical'),
                                    ])
                                    ->required()
                                    ->disabled(),
                                DateTimePicker::make('resolved_at')
                                    ->label(__('firewall.abuse_incidents.resolved_at'))
                                    ->disabled(),
                            ]),
                    ]),

                \Filament\Schemas\Components\Section::make(__('firewall.abuse_incidents.target_information'))
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(3)
                            ->schema([
                                TextInput::make('ip_address')
                                    ->label(__('firewall.abuse_incidents.ip_address'))
                                    ->disabled(),
                                TextInput::make('email_hash')
                                    ->label(__('firewall.abuse_incidents.email_hash'))
                                    ->disabled()
                                    ->helperText(__('firewall.abuse_incidents.email_hash_helper')),
                                TextInput::make('domain')
                                    ->label(__('firewall.abuse_incidents.domain'))
                                    ->disabled(),
                            ]),
                    ]),

                \Filament\Schemas\Components\Section::make(__('firewall.abuse_incidents.details'))
                    ->schema([
                        Textarea::make('description')
                            ->label(__('firewall.abuse_incidents.description'))
                            ->rows(3)
                            ->disabled(),
                        Textarea::make('metadata')
                            ->label(__('firewall.abuse_incidents.metadata'))
                            ->rows(5)
                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT))
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('incident_type')
                    ->label(__('firewall.abuse_incidents.type'))
                    ->formatStateUsing(fn ($record) => $record->incident_type_label)
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('ip_address')
                    ->label(__('firewall.abuse_incidents.ip'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('email_hash')
                    ->label(__('firewall.abuse_incidents.email_hash'))
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $state ? substr($state, 0, 12).'...' : '-')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('domain')
                    ->label(__('firewall.abuse_incidents.domain'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->placeholder('-'),

                TextColumn::make('severity')
                    ->label(__('firewall.abuse_incidents.severity'))
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->severity_color),

                TextColumn::make('description')
                    ->label(__('firewall.abuse_incidents.description'))
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->description)
                    ->wrap(),

                IconColumn::make('resolved_at')
                    ->label(__('firewall.abuse_incidents.resolved'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('firewall.abuse_incidents.occurred_at'))
                    ->dateTime()
                    ->sortable()
                    ->since(),

                TextColumn::make('resolved_at')
                    ->label(__('firewall.abuse_incidents.resolved_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('incident_type')
                    ->label(__('firewall.abuse_incidents.incident_type'))
                    ->options([
                        'rate_limit_exceeded' => __('firewall.abuse_incidents.types.rate_limit_exceeded'),
                        'ip_spoofing_attempt' => __('firewall.abuse_incidents.types.ip_spoofing_attempt'),
                        'otp_bruteforce' => __('firewall.abuse_incidents.types.otp_bruteforce'),
                        'honeypot_triggered' => __('firewall.abuse_incidents.types.honeypot_triggered'),
                        'invalid_otp_attempts' => __('firewall.abuse_incidents.types.invalid_otp_attempts'),
                        'ip_mismatch' => __('firewall.abuse_incidents.types.ip_mismatch'),
                        'suspicious_pattern' => __('firewall.abuse_incidents.types.suspicious_pattern'),
                        'other' => __('firewall.abuse_incidents.types.other'),
                    ]),

                SelectFilter::make('severity')
                    ->label(__('firewall.abuse_incidents.severity'))
                    ->options([
                        'low' => __('firewall.abuse_incidents.severity_levels.low'),
                        'medium' => __('firewall.abuse_incidents.severity_levels.medium'),
                        'high' => __('firewall.abuse_incidents.severity_levels.high'),
                        'critical' => __('firewall.abuse_incidents.severity_levels.critical'),
                    ]),

                TernaryFilter::make('resolved')
                    ->label(__('firewall.abuse_incidents.status'))
                    ->placeholder(__('firewall.abuse_incidents.all_incidents'))
                    ->trueLabel(__('firewall.abuse_incidents.resolved'))
                    ->falseLabel(__('firewall.abuse_incidents.unresolved'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('resolved_at'),
                        false: fn (Builder $query) => $query->whereNull('resolved_at'),
                    ),

                Filter::make('ip_address')
                    ->schema([
                        TextInput::make('ip_address')
                            ->label(__('firewall.abuse_incidents.ip_address'))
                            ->placeholder('e.g., 192.168.1.1'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['ip_address'],
                            fn (Builder $query, $ip): Builder => $query->where('ip_address', 'like', "%{$ip}%")
                        );
                    }),

                Filter::make('email_hash')
                    ->schema([
                        TextInput::make('email_hash')
                            ->label(__('firewall.abuse_incidents.email_hash'))
                            ->placeholder('SHA-256 hash'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['email_hash'],
                            fn (Builder $query, $hash): Builder => $query->where('email_hash', 'like', "%{$hash}%")
                        );
                    }),

                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('created_from')
                            ->label(__('firewall.abuse_incidents.from')),
                        DatePicker::make('created_until')
                            ->label(__('firewall.abuse_incidents.until')),
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
                Action::make('resolve')
                    ->label(__('firewall.abuse_incidents.resolve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('firewall.abuse_incidents.resolve_heading'))
                    ->modalDescription(__('firewall.abuse_incidents.resolve_description'))
                    ->action(function (AbuseIncident $record) {
                        $record->resolve();

                        Notification::make()
                            ->title(__('firewall.abuse_incidents.incident_resolved'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (AbuseIncident $record) => ! $record->isResolved()),
                Action::make('unresolve')
                    ->label(__('firewall.abuse_incidents.unresolve'))
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (AbuseIncident $record) {
                        $record->update(['resolved_at' => null]);

                        Notification::make()
                            ->title(__('firewall.abuse_incidents.incident_unresolved'))
                            ->warning()
                            ->send();
                    })
                    ->visible(fn (AbuseIncident $record) => $record->isResolved()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('resolve')
                        ->label(__('firewall.abuse_incidents.mark_as_resolved'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->resolve();

                            Notification::make()
                                ->title(__('firewall.abuse_incidents.incidents_resolved'))
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s'); // Auto-refresh every 30 seconds
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
            'index' => ListAbuseIncidents::route('/'),
            'view' => ViewAbuseIncident::route('/{record}'),
        ];
    }

    /**
     * Get navigation badge (count of unresolved critical/high incidents)
     */
    public static function getNavigationBadge(): ?string
    {
        $count = AbuseIncident::unresolved()
            ->whereIn('severity', ['critical', 'high'])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * Set badge color based on severity
     */
    public static function getNavigationBadgeColor(): ?string
    {
        $hasCritical = AbuseIncident::unresolved()
            ->where('severity', 'critical')
            ->exists();

        return $hasCritical ? 'danger' : 'warning';
    }
}
