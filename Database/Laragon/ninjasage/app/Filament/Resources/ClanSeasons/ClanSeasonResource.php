<?php

namespace App\Filament\Resources\ClanSeasons;

use App\Filament\Resources\ClanSeasons\Pages;
use App\Models\ClanSeason;
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

class ClanSeasonResource extends Resource
{
    protected static ?string $model = ClanSeason::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|\UnitEnum|null $navigationGroup = 'Seasons';

    protected static ?string $navigationLabel = 'Clan Seasons';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Season Info')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('number')
                            ->label('Season Number')
                            ->numeric()
                            ->required()
                            ->default(fn () => (ClanSeason::max('number') ?? 0) + 1),

                        Forms\Components\Toggle::make('active')
                            ->label('Active')
                            ->helperText('Only one season should be active at a time.')
                            ->default(false),
                    ]),

                Section::make('Duration')
                    ->columns(2)
                    ->schema([
                        Forms\Components\DateTimePicker::make('started_at')
                            ->label('Start Date')
                            ->default(now())
                            ->required(),

                        Forms\Components\DateTimePicker::make('ended_at')
                            ->label('End Date')
                            ->helperText('Leave blank for no fixed end. The in-game countdown timer will count down to this date.')
                            ->nullable()
                            ->afterOrEqual('started_at'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('number', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->label('Active'),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ended_at')
                    ->label('Ends')
                    ->dateTime()
                    ->placeholder('No end date')
                    ->sortable(),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->state(function (ClanSeason $record): string {
                        if (!$record->ended_at) return '∞';
                        $end   = $record->ended_at;
                        $start = $record->started_at ?? $record->created_at;
                        $days  = (int) $start->diffInDays($end);
                        return $days . 'd';
                    }),

                Tables\Columns\TextColumn::make('time_left')
                    ->label('Time Left')
                    ->state(function (ClanSeason $record): string {
                        if (!$record->active) return '-';
                        if (!$record->ended_at) return 'No end date';
                        $seconds = max(0, $record->ended_at->timestamp - now()->timestamp);
                        if ($seconds === 0) return 'Ended';
                        $d = floor($seconds / 86400);
                        $h = floor(($seconds % 86400) / 3600);
                        $m = floor(($seconds % 3600) / 60);
                        return "{$d}d {$h}h {$m}m";
                    })
                    ->badge()
                    ->color(fn (ClanSeason $record): string => !$record->active ? 'gray' : (
                        $record->ended_at && $record->ended_at->isPast() ? 'danger' : 'success'
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
                    ->action(function (ClanSeason $record, array $data): void {
                        $record->update([
                            'active'   => false,
                            'ended_at' => now(),
                        ]);

                        ClanSeason::create([
                            'number'     => $record->number + 1,
                            'active'     => true,
                            'started_at' => now(),
                            'ended_at'   => now()->addDays((int) $data['duration_days']),
                        ]);

                        Notification::make()
                            ->title('Season advanced')
                            ->body("Season {$record->number} ended. Season " . ($record->number + 1) . " started.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (ClanSeason $record): bool => (bool) $record->active),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListClanSeasons::route('/'),
            'create' => Pages\CreateClanSeason::route('/create'),
            'edit'   => Pages\EditClanSeason::route('/{record}/edit'),
        ];
    }
}