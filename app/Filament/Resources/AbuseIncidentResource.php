<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AbuseIncidentResource\Pages;
use App\Models\AbuseIncident;
use Filament\Forms\Components\{DateTimePicker, Grid, Section, Select, TextInput, Textarea};
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AbuseIncidentResource extends Resource
{
    protected static ?string $model = AbuseIncident::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Abuse Incidents';

    protected static ?string $navigationGroup = 'Simple Unblock Security';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Incident Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('incident_type')
                                    ->label('Incident Type')
                                    ->options([
                                        'rate_limit_exceeded' => 'Rate Limit Exceeded',
                                        'ip_spoofing_attempt' => 'IP Spoofing Attempt',
                                        'otp_bruteforce' => 'OTP Brute Force',
                                        'honeypot_triggered' => 'Honeypot Triggered',
                                        'invalid_otp_attempts' => 'Invalid OTP Attempts',
                                        'ip_mismatch' => 'IP Mismatch',
                                        'suspicious_pattern' => 'Suspicious Pattern',
                                        'other' => 'Other',
                                    ])
                                    ->required()
                                    ->disabled(),
                                Select::make('severity')
                                    ->label('Severity')
                                    ->options([
                                        'low' => 'Low',
                                        'medium' => 'Medium',
                                        'high' => 'High',
                                        'critical' => 'Critical',
                                    ])
                                    ->required()
                                    ->disabled(),
                            ]),
                    ]),

                Section::make('Target Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('ip_address')
                                    ->label('IP Address')
                                    ->disabled(),
                                TextInput::make('email_hash')
                                    ->label('Email Hash')
                                    ->disabled()
                                    ->helperText('SHA-256 hash (GDPR compliant)'),
                                TextInput::make('domain')
                                    ->label('Domain')
                                    ->disabled(),
                            ]),
                    ]),

                Section::make('Details')
                    ->schema([
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->disabled(),
                        Textarea::make('metadata')
                            ->label('Metadata (JSON)')
                            ->rows(5)
                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT))
                            ->disabled(),
                    ]),

                Section::make('Resolution')
                    ->schema([
                        DateTimePicker::make('resolved_at')
                            ->label('Resolved At')
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('incident_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($record) => $record->incident_type_label)
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('email_hash')
                    ->label('Email Hash')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $state ? substr($state, 0, 12).'...' : '-')
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('domain')
                    ->label('Domain')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('severity')
                    ->label('Severity')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->severity_color),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->description)
                    ->wrap(),

                Tables\Columns\IconColumn::make('resolved_at')
                    ->label('Resolved')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Occurred At')
                    ->dateTime()
                    ->sortable()
                    ->since(),

                Tables\Columns\TextColumn::make('resolved_at')
                    ->label('Resolved At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('incident_type')
                    ->label('Incident Type')
                    ->options([
                        'rate_limit_exceeded' => 'Rate Limit Exceeded',
                        'ip_spoofing_attempt' => 'IP Spoofing Attempt',
                        'otp_bruteforce' => 'OTP Brute Force',
                        'honeypot_triggered' => 'Honeypot Triggered',
                        'invalid_otp_attempts' => 'Invalid OTP Attempts',
                        'ip_mismatch' => 'IP Mismatch',
                        'suspicious_pattern' => 'Suspicious Pattern',
                        'other' => 'Other',
                    ]),

                Tables\Filters\SelectFilter::make('severity')
                    ->label('Severity')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ]),

                Tables\Filters\TernaryFilter::make('resolved')
                    ->label('Status')
                    ->placeholder('All incidents')
                    ->trueLabel('Resolved')
                    ->falseLabel('Unresolved')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('resolved_at'),
                        false: fn (Builder $query) => $query->whereNull('resolved_at'),
                    ),

                Tables\Filters\Filter::make('ip_address')
                    ->form([
                        TextInput::make('ip_address')
                            ->label('IP Address')
                            ->placeholder('e.g., 192.168.1.1'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['ip_address'],
                            fn (Builder $query, $ip): Builder => $query->where('ip_address', 'like', "%{$ip}%")
                        );
                    }),

                Tables\Filters\Filter::make('email_hash')
                    ->form([
                        TextInput::make('email_hash')
                            ->label('Email Hash')
                            ->placeholder('SHA-256 hash'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['email_hash'],
                            fn (Builder $query, $hash): Builder => $query->where('email_hash', 'like', "%{$hash}%")
                        );
                    }),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('created_from')
                            ->label('From'),
                        \Filament\Forms\Components\DatePicker::make('created_until')
                            ->label('Until'),
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
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Resolve Incident')
                    ->modalDescription('Mark this incident as resolved?')
                    ->action(function (AbuseIncident $record) {
                        $record->resolve();

                        Notification::make()
                            ->title('Incident resolved')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (AbuseIncident $record) => ! $record->isResolved()),
                Tables\Actions\Action::make('unresolve')
                    ->label('Unresolve')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (AbuseIncident $record) {
                        $record->update(['resolved_at' => null]);

                        Notification::make()
                            ->title('Incident marked as unresolved')
                            ->warning()
                            ->send();
                    })
                    ->visible(fn (AbuseIncident $record) => $record->isResolved()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('resolve')
                        ->label('Mark as Resolved')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->resolve();

                            Notification::make()
                                ->title('Incidents resolved')
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
            'index' => Pages\ListAbuseIncidents::route('/'),
            'view' => Pages\ViewAbuseIncident::route('/{record}'),
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
