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

    #[\Override]
    public static function getNavigationGroup(): ?string
    {
        return __('pattern_detections.Analytics');
    }

    protected static ?int $navigationSort = 1;

    #[\Override]
    public static function getNavigationLabel(): string
    {
        return __('pattern_detections.Pattern Detections');
    }

    #[\Override]
    public static function getModelLabel(): string
    {
        return __('pattern_detections.Pattern Detection');
    }

    #[\Override]
    public static function getPluralModelLabel(): string
    {
        return __('pattern_detections.Pattern Detections');
    }

    public static function schema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('pattern_detections.Pattern Information'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('pattern_type')
                                    ->label(__('pattern_detections.Pattern Type'))
                                    ->options([
                                        PatternDetection::TYPE_DISTRIBUTED_ATTACK => __('pattern_detections.Distributed Attack'),
                                        PatternDetection::TYPE_SUBNET_SCAN => __('pattern_detections.Subnet Scan'),
                                        PatternDetection::TYPE_ANOMALY => __('pattern_detections.Traffic Anomaly'),
                                        PatternDetection::TYPE_OTHER => __('pattern_detections.Other'),
                                    ])
                                    ->required()
                                    ->disabled(),
                                Select::make('severity')
                                    ->label(__('pattern_detections.Severity'))
                                    ->options([
                                        PatternDetection::SEVERITY_LOW => __('pattern_detections.Low'),
                                        PatternDetection::SEVERITY_MEDIUM => __('pattern_detections.Medium'),
                                        PatternDetection::SEVERITY_HIGH => __('pattern_detections.High'),
                                        PatternDetection::SEVERITY_CRITICAL => __('pattern_detections.Critical'),
                                    ])
                                    ->required()
                                    ->disabled(),
                                TextInput::make('confidence')
                                    ->label(__('pattern_detections.Confidence'))
                                    ->suffix('%')
                                    ->disabled(),
                            ]),
                    ]),

                Section::make(__('pattern_detections.Details'))
                    ->schema([
                        Textarea::make('description')
                            ->label(__('pattern_detections.Description'))
                            ->rows(3)
                            ->disabled(),
                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('detected_at')
                                    ->label(__('pattern_detections.Detected At'))
                                    ->disabled(),
                                DateTimePicker::make('resolved_at')
                                    ->label(__('pattern_detections.Resolved At'))
                                    ->disabled(),
                            ]),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pattern_type')
                    ->label(__('pattern_detections.Type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        PatternDetection::TYPE_DISTRIBUTED_ATTACK => __('pattern_detections.Distributed Attack'),
                        PatternDetection::TYPE_SUBNET_SCAN => __('pattern_detections.Subnet Scan'),
                        PatternDetection::TYPE_ANOMALY => __('pattern_detections.Traffic Anomaly'),
                        PatternDetection::TYPE_OTHER => __('pattern_detections.Other'),
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
                    ->label(__('pattern_detections.Severity'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        PatternDetection::SEVERITY_CRITICAL => __('pattern_detections.Critical'),
                        PatternDetection::SEVERITY_HIGH => __('pattern_detections.High'),
                        PatternDetection::SEVERITY_MEDIUM => __('pattern_detections.Medium'),
                        PatternDetection::SEVERITY_LOW => __('pattern_detections.Low'),
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        PatternDetection::SEVERITY_CRITICAL => 'danger',
                        PatternDetection::SEVERITY_HIGH => 'warning',
                        PatternDetection::SEVERITY_MEDIUM => 'warning',
                        PatternDetection::SEVERITY_LOW => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('confidence')
                    ->label(__('pattern_detections.Confidence'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => number_format($state, 1).'%')
                    ->color(fn (float $state): string => match (true) {
                        $state >= 75 => 'success',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),

                TextColumn::make('affected_count')
                    ->label(__('pattern_detections.Affected IPs/Emails'))
                    ->getStateUsing(function ($record) {
                        $data = $record->pattern_data;
                        $ips = count($data['affected_ips'] ?? []);
                        $emails = count($data['affected_emails'] ?? []);

                        return $ips > 0 ? "{$ips} IPs" : "{$emails} emails";
                    }),

                TextColumn::make('detected_at')
                    ->label(__('pattern_detections.Detected'))
                    ->dateTime()
                    ->sortable()
                    ->since(),

                TextColumn::make('resolved_at')
                    ->label(__('pattern_detections.Status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? __('pattern_detections.Resolved') : __('pattern_detections.Active'))
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('pattern_type')
                    ->label(__('pattern_detections.Pattern Type'))
                    ->options([
                        PatternDetection::TYPE_DISTRIBUTED_ATTACK => __('pattern_detections.Distributed Attack'),
                        PatternDetection::TYPE_SUBNET_SCAN => __('pattern_detections.Subnet Scan'),
                        PatternDetection::TYPE_ANOMALY => __('pattern_detections.Traffic Anomaly'),
                        PatternDetection::TYPE_OTHER => __('pattern_detections.Other'),
                    ]),

                SelectFilter::make('severity')
                    ->label(__('pattern_detections.Severity'))
                    ->options([
                        PatternDetection::SEVERITY_CRITICAL => __('pattern_detections.Critical'),
                        PatternDetection::SEVERITY_HIGH => __('pattern_detections.High'),
                        PatternDetection::SEVERITY_MEDIUM => __('pattern_detections.Medium'),
                        PatternDetection::SEVERITY_LOW => __('pattern_detections.Low'),
                    ])
                    ->multiple(),

                TernaryFilter::make('resolved')
                    ->label(__('pattern_detections.Status'))
                    ->nullable()
                    ->trueLabel(__('pattern_detections.Resolved'))
                    ->falseLabel(__('pattern_detections.Active'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('resolved_at'),
                        false: fn (Builder $query) => $query->whereNull('resolved_at'),
                    ),
            ])
            ->actions([
                Action::make('resolve')
                    ->label(__('pattern_detections.Resolve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => ! $record->isResolved())
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->resolve()),

                Action::make('unresolve')
                    ->label(__('pattern_detections.Reopen'))
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

    #[\Override]
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPatternDetections::route('/'),
            'view' => Pages\ViewPatternDetection::route('/{record}'),
        ];
    }
}
