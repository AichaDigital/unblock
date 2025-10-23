<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\EmailReputationResource\Pages;
use App\Models\EmailReputation;
use Filament\Forms\Components\{Grid, Section, TextInput, Textarea};
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmailReputationResource extends Resource
{
    protected static ?string $model = EmailReputation::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Email Reputation';

    protected static ?string $navigationGroup = 'Simple Unblock Security';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Email Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('email_hash')
                                    ->label('Email Hash (SHA-256)')
                                    ->required()
                                    ->disabled()
                                    ->helperText('GDPR compliant - stores hash, not plaintext'),
                                TextInput::make('email_domain')
                                    ->label('Email Domain')
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
                                TextInput::make('verified_requests')
                                    ->label('Verified Requests')
                                    ->numeric()
                                    ->disabled(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('failed_requests')
                                    ->label('Failed Requests')
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
                            ->helperText('Add investigation notes or context about this email address.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email_hash')
                    ->label('Email Hash')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => substr($state, 0, 16).'...')
                    ->copyable()
                    ->copyMessage('Full hash copied!')
                    ->copyMessageDuration(1500),

                Tables\Columns\TextColumn::make('email_domain')
                    ->label('Domain')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

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

                Tables\Columns\TextColumn::make('verified_requests')
                    ->label('Verified')
                    ->sortable()
                    ->alignCenter()
                    ->color('success'),

                Tables\Columns\TextColumn::make('failed_requests')
                    ->label('Failed')
                    ->sortable()
                    ->alignCenter()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('verification_rate')
                    ->label('Verification %')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("(verified_requests / NULLIF(total_requests, 0)) {$direction}");
                    })
                    ->formatStateUsing(fn ($record) => number_format($record->verification_rate, 2).'%')
                    ->badge()
                    ->color(fn ($record) => $record->verification_rate >= 70 ? 'success' : ($record->verification_rate >= 40 ? 'warning' : 'danger'))
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

                Tables\Filters\SelectFilter::make('email_domain')
                    ->label('Email Domain')
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
                        'tableFilters[email_hash][value]' => $record->email_hash,
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
            'index' => Pages\ListEmailReputations::route('/'),
            'view' => Pages\ViewEmailReputation::route('/{record}'),
        ];
    }
}
