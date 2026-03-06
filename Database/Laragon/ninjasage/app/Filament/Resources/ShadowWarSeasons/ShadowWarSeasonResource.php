<?php

namespace App\Filament\Resources\ShadowWarSeasons;

use App\Filament\Resources\ShadowWarSeasons\Pages;
use App\Models\ShadowWarSeason;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class ShadowWarSeasonResource extends Resource
{
    protected static ?string $model = ShadowWarSeason::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|\UnitEnum|null $navigationGroup = 'Seasons';

    protected static ?string $navigationLabel = 'Shadow War Seasons';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Season Info')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('num')
                            ->label('Season Number')
                            ->numeric()
                            ->required()
                            ->default(fn () => (ShadowWarSeason::max('num') ?? 0) + 1),

                        Forms\Components\Toggle::make('active')
                            ->label('Active')
                            ->helperText('Only one season should be active at a time.')
                            ->default(false),
                    ]),

                Section::make('Duration')
                    ->columns(2)
                    ->schema([
                        Forms\Components\DateTimePicker::make('start_at')
                            ->label('Start Date')
                            ->default(now())
                            ->required(),

                        Forms\Components\DateTimePicker::make('end_at')
                            ->label('End Date')
                            ->helperText('Leave blank for no fixed end. The in-game countdown timer will count down to this date.')
                            ->nullable()
                            ->afterOrEqual('start_at'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('num', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('num')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->label('Active'),

                Tables\Columns\TextColumn::make('start_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_at')
                    ->label('Ends')
                    ->dateTime()
                    ->placeholder('No end date')
                    ->sortable(),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->state(function (ShadowWarSeason $record): string {
                        if (!$record->end_at) return '∞';
                        $start = $record->start_at ?? $record->created_at;
                        $days  = (int) $start->diffInDays($record->end_at);
                        return $days . 'd';
                    }),

                Tables\Columns\TextColumn::make('time_left')
                    ->label('Time Left')
                    ->state(function (ShadowWarSeason $record): string {
                        if (!$record->active) return '-';
                        if (!$record->end_at) return 'No end date';
                        $seconds = max(0, $record->end_at->timestamp - now()->timestamp);
                        if ($seconds === 0) return 'Ended';
                        $d = floor($seconds / 86400);
                        $h = floor(($seconds % 86400) / 3600);
                        $m = floor(($seconds % 3600) / 60);
                        return "{$d}d {$h}h {$m}m";
                    })
                    ->badge()
                    ->color(fn (ShadowWarSeason $record): string => !$record->active ? 'gray' : (
                        $record->end_at && $record->end_at->isPast() ? 'danger' : 'success'
                    )),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
                Action::make('end_and_advance')
                    ->label('End & Start New')
                    ->icon(Heroicon::OutlinedForward)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('End Season & Start New')
                    ->modalDescription('This will end the current season and immediately start a new one.')
                    ->form([
                        Forms\Components\TextInput::make('duration_days')
                            ->label('New Season Duration (days)')
                            ->numeric()
                            ->minValue(1)
                            ->default(30)
                            ->required(),
                    ])
                    ->action(function (ShadowWarSeason $record, array $data): void {
                        $record->update([
                            'active' => false,
                            'end_at' => now(),
                        ]);

                        ShadowWarSeason::create([
                            'num'      => $record->num + 1,
                            'active'   => true,
                            'start_at' => now(),
                            'end_at'   => now()->addDays((int) $data['duration_days']),
                        ]);

                        Notification::make()
                            ->title('Season advanced')
                            ->body("Season {$record->num} ended. Season " . ($record->num + 1) . " started.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (ShadowWarSeason $record): bool => (bool) $record->active),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListShadowWarSeasons::route('/'),
            'create' => Pages\CreateShadowWarSeason::route('/create'),
            'edit'   => Pages\EditShadowWarSeason::route('/{record}/edit'),
        ];
    }
}
