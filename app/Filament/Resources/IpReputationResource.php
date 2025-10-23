<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\IpReputationResource\Pages;
use App\Models\IpReputation;
use Filament\Forms\Components\{Grid, Section, TextInput, Textarea};
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IpReputationResource extends Resource
{
    protected static ?string $model = IpReputation::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'IP Reputation';

    protected static ?string $navigationGroup = 'Simple Unblock Security';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('IP Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('ip')
                                    ->label('IP Address')
                                    ->required()
                                    ->disabled(),
                                TextInput::make('subnet')
                                    ->label('Subnet')
                                    ->disabled(),
                            ]),
                    ]),

                Section::make('Reputation & Statistics')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('reputation_score')
                                    ->label('Reputation Score')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('/100')
                                    ->disabled(),
                                TextInput::make('total_requests')
                                    ->label('Total Requests')
                                    ->numeric()
                                    ->disabled(),
                                TextInput::make('failed_requests')
                                    ->label('Failed Requests')
                                    ->numeric()
                                    ->disabled(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('blocked_count')
                                    ->label('Blocked Count')
                                    ->numeric()
                                    ->disabled(),
                                TextInput::make('last_seen_at')
                                    ->label('Last Seen')
                                    ->disabled(),
                            ]),
                    ]),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Admin Notes')
                            ->rows(4)
                            ->helperText('Add investigation notes or context about this IP address.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ip')
                    ->label('IP Address')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('subnet')
                    ->label('Subnet')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('reputation_score')
                    ->label('Score')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->reputation_color)
                    ->formatStateUsing(fn ($state) => $state.'/100'),

                Tables\Columns\TextColumn::make('total_requests')
                    ->label('Total')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('failed_requests')
                    ->label('Failed')
                    ->sortable()
                    ->alignCenter()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('blocked_count')
                    ->label('Blocked')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('success_rate')
                    ->label('Success %')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("(1 - (failed_requests / NULLIF(total_requests, 0))) {$direction}");
                    })
                    ->formatStateUsing(fn ($record) => number_format($record->success_rate, 2).'%')
                    ->badge()
                    ->color(fn ($record) => $record->success_rate >= 80 ? 'success' : ($record->success_rate >= 50 ? 'warning' : 'danger'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->since(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('First Seen')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('reputation_score')
                    ->label('Reputation')
                    ->options([
                        'high' => 'High (80-100)',
                        'medium' => 'Medium (50-79)',
                        'low' => 'Low (0-49)',
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

                Tables\Filters\Filter::make('subnet')
                    ->form([
                        TextInput::make('subnet')
                            ->label('Subnet Search')
                            ->placeholder('e.g., 192.168.1'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['subnet'],
                            fn (Builder $query, $subnet): Builder => $query->where('subnet', 'like', "%{$subnet}%")
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
                Tables\Actions\EditAction::make()
                    ->visible(fn () => false), // Disable edit for now (read-only except notes)
                Tables\Actions\Action::make('viewIncidents')
                    ->label('Incidents')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->url(fn ($record) => route('filament.admin.resources.abuse-incidents.index', [
                        'tableFilters[ip_address][value]' => $record->ip,
                    ]))
                    ->color('warning'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
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
            'index' => Pages\ListIpReputations::route('/'),
            'view' => Pages\ViewIpReputation::route('/{record}'),
        ];
    }
}
