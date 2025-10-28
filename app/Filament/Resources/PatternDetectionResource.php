<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PatternDetectionResource\Pages;
use App\Models\PatternDetection;
use Filament\Actions\{Action, BulkActionGroup, DeleteBulkAction};
use Filament\Forms\Components\{DateTimePicker, Grid, Section, Select, TextInput, Textarea};
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\{SelectFilter, TernaryFilter};
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PatternDetectionResource extends Resource
{
    protected static ?string $model = PatternDetection::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    public static function getNavigationGroup(): ?string
    {
        return 'Analytics';
    }

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('Pattern Detections');
    }

    public static function getModelLabel(): string
    {
        return __('Pattern Detection');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Pattern Detections');
    }

    public static function schema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pattern Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('pattern_type')
                                    ->label('Pattern Type')
                                    ->options([
                                        PatternDetection::TYPE_DISTRIBUTED_ATTACK => 'Distributed Attack',
                                        PatternDetection::TYPE_SUBNET_SCAN => 'Subnet Scan',
                                        PatternDetection::TYPE_ANOMALY => 'Traffic Anomaly',
                                        PatternDetection::TYPE_OTHER => 'Other',
                                    ])
                                    ->required()
                                    ->disabled(),
                                Select::make('severity')
                                    ->label('Severity')
                                    ->options([
                                        PatternDetection::SEVERITY_LOW => 'Low',
                                        PatternDetection::SEVERITY_MEDIUM => 'Medium',
                                        PatternDetection::SEVERITY_HIGH => 'High',
                                        PatternDetection::SEVERITY_CRITICAL => 'Critical',
                                    ])
                                    ->required()
                                    ->disabled(),
                                TextInput::make('confidence')
                                    ->label('Confidence')
                                    ->suffix('%')
                                    ->disabled(),
                            ]),
                    ]),

                Section::make('Details')
                    ->schema([
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->disabled(),
                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('detected_at')
                                    ->label('Detected At')
                                    ->disabled(),
                                DateTimePicker::make('resolved_at')
                                    ->label('Resolved At')
                                    ->disabled(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pattern_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        PatternDetection::TYPE_DISTRIBUTED_ATTACK => 'Distributed Attack',
                        PatternDetection::TYPE_SUBNET_SCAN => 'Subnet Scan',
                        PatternDetection::TYPE_ANOMALY => 'Traffic Anomaly',
                        PatternDetection::TYPE_OTHER => 'Other',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        PatternDetection::TYPE_DISTRIBUTED_ATTACK => 'danger',
                        PatternDetection::TYPE_SUBNET_SCAN => 'warning',
                        PatternDetection::TYPE_ANOMALY => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('severity')
                    ->label('Severity')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        PatternDetection::SEVERITY_CRITICAL => 'danger',
                        PatternDetection::SEVERITY_HIGH => 'warning',
                        PatternDetection::SEVERITY_MEDIUM => 'warning',
                        PatternDetection::SEVERITY_LOW => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('confidence')
                    ->label('Confidence')
                    ->badge()
                    ->formatStateUsing(fn ($state) => number_format($state, 1).'%')
                    ->color(fn (float $state): string => match (true) {
                        $state >= 75 => 'success',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),

                TextColumn::make('affected_count')
                    ->label('Affected IPs/Emails')
                    ->getStateUsing(function ($record) {
                        $data = $record->pattern_data;
                        $ips = count($data['affected_ips'] ?? []);
                        $emails = count($data['affected_emails'] ?? []);

                        return $ips > 0 ? "{$ips} IPs" : "{$emails} emails";
                    }),

                TextColumn::make('detected_at')
                    ->label('Detected')
                    ->dateTime()
                    ->sortable()
                    ->since(),

                TextColumn::make('resolved_at')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Resolved' : 'Active')
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('pattern_type')
                    ->label('Pattern Type')
                    ->options([
                        PatternDetection::TYPE_DISTRIBUTED_ATTACK => 'Distributed Attack',
                        PatternDetection::TYPE_SUBNET_SCAN => 'Subnet Scan',
                        PatternDetection::TYPE_ANOMALY => 'Traffic Anomaly',
                        PatternDetection::TYPE_OTHER => 'Other',
                    ]),

                SelectFilter::make('severity')
                    ->label('Severity')
                    ->options([
                        PatternDetection::SEVERITY_CRITICAL => 'Critical',
                        PatternDetection::SEVERITY_HIGH => 'High',
                        PatternDetection::SEVERITY_MEDIUM => 'Medium',
                        PatternDetection::SEVERITY_LOW => 'Low',
                    ])
                    ->multiple(),

                TernaryFilter::make('resolved')
                    ->label('Status')
                    ->nullable()
                    ->trueLabel('Resolved')
                    ->falseLabel('Active')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('resolved_at'),
                        false: fn (Builder $query) => $query->whereNull('resolved_at'),
                    ),
            ])
            ->actions([
                Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => ! $record->isResolved())
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->resolve()),

                Action::make('unresolve')
                    ->label('Reopen')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn ($record) => $record->isResolved())
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['resolved_at' => null])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('detected_at', 'desc');
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
            'index' => Pages\ListPatternDetections::route('/'),
            'view' => Pages\ViewPatternDetection::route('/{record}'),
        ];
    }
}
