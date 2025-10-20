<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReportResource\{Pages};
use App\Models\Report;
use Filament\Forms\Form;
use Filament\{Forms, Tables};
use Filament\Resources\Resource;
use Filament\Tables\Table;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('firewall.reports.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('firewall.reports.title');
    }

    public static function getPluralModelLabel(): string
    {
        return __('firewall.reports.title');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('firewall.reports.title'))
                    ->schema([
                        Forms\Components\Placeholder::make('user.name')
                            ->label(__('firewall.reports.user'))
                            ->content(fn ($record) => $record->user?->name ?? __('firewall.reports.unassigned'))
                            ->inlineLabel(),

                        Forms\Components\Placeholder::make('host.fqdn')
                            ->label(__('firewall.reports.host'))
                            ->content(fn ($record) => $record->host?->fqdn ?? __('firewall.reports.unassigned'))
                            ->inlineLabel(),

                        Forms\Components\TextInput::make('ip')
                            ->label(__('firewall.reports.ip'))
                            ->disabled()
                            ->inlineLabel(),

                        Forms\Components\DateTimePicker::make('created_at')
                            ->label(__('firewall.reports.created_at'))
                            ->disabled()
                            ->inlineLabel(),
                    ])
                    ->columns(1),

                Forms\Components\Section::make(__('firewall.reports.logs'))
                    ->schema([
                        Forms\Components\Textarea::make('logs')
                            ->label(__('firewall.reports.logs'))
                            ->disabled()
                            ->rows(10)
                            ->columnSpanFull()
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                Forms\Components\Section::make(__('firewall.reports.analysis'))
                    ->schema([
                        Forms\Components\Textarea::make('analysis')
                            ->label(__('firewall.reports.analysis'))
                            ->disabled()
                            ->rows(10)
                            ->columnSpanFull()
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state),
                    ])
                    ->collapsible()
                    ->collapsed(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip')
                    ->label(__('firewall.reports.ip'))
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('firewall.reports.user'))
                    ->searchable(['users.first_name', 'users.last_name'])
                    ->sortable()
                    ->placeholder(__('firewall.reports.unassigned')),

                Tables\Columns\TextColumn::make('host.fqdn')
                    ->label(__('firewall.reports.host'))
                    ->searchable()
                    ->sortable()
                    ->placeholder(__('firewall.reports.unassigned')),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('firewall.reports.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('firewall.reports.last_read'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label(__('firewall.reports.user'))
                    ->relationship('user', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('host_id')
                    ->label(__('firewall.reports.host'))
                    ->relationship('host', 'fqdn')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('created_at')
                    ->label(__('firewall.reports.created_at'))
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('firewall.reports.created_from')),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('firewall.reports.created_until')),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn ($query, $date) => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn ($query, $date) => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('firewall.reports.title'))
            ->emptyStateDescription(__('firewall.reports.empty_state'))
            ->emptyStateIcon('heroicon-o-document-text');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageReports::route('/'),
        ];
    }
}
