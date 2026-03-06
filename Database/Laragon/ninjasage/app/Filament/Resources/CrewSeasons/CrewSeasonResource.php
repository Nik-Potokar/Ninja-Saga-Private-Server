<?php

namespace App\Filament\Resources\CrewSeasons;

use App\Filament\Resources\CrewSeasons\Pages;
use App\Models\CrewSeason;
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

class CrewSeasonResource extends Resource
{
    protected static ?string $model = CrewSeason::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Seasons';

    protected static ?string $navigationLabel = 'Crew Seasons';

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
                            ->default(fn () => (CrewSeason::max('number') ?? 0) + 1),

                        Forms\Components\Toggle::make('active')
                            ->label('Active')
                            ->helperText('Only one season should be active at a time.')
                            ->default(false),

                        Forms\Components\Select::make('phase')
                            ->label('Current Phase')
                            ->options([
                                1 => 'Phase 1 — Attack (vs Bosses)',
                                2 => 'Phase 2 — Defend (Castle Wars)',
                            ])
                            ->required()
                            ->default(1),
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
                            ->helperText('Leave blank for no fixed end. The in-game countdown counts down to this date.')
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

                Tables\Columns\TextColumn::make('phase')
                    ->label('Phase')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        1 => 'Phase 1 (Attack)',
                        2 => 'Phase 2 (Defend)',
                        default => "Phase {$state}",
                    })
                    ->color(fn (int $state): string => $state === 1 ? 'info' : 'warning'),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ended_at')
                    ->label('Ends')
                    ->dateTime()
                    ->placeholder('No end date')
                    ->sortable(),

                Tables\Columns\TextColumn::make('time_left')
                    ->label('Time Left')
                    ->state(function (CrewSeason $record): string {
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
                    ->color(fn (CrewSeason $record): string => !$record->active ? 'gray' : (
                        $record->ended_at && $record->ended_at->isPast() ? 'danger' : 'success'
                    )),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),

                Action::make('advance_phase')
                    ->label('Advance to Phase 2')
                    ->icon(Heroicon::OutlinedArrowRight)
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Advance to Phase 2')
                    ->modalDescription('Switch the active season from Phase 1 (Attack) to Phase 2 (Defend). Players will immediately enter castle defense mode.')
                    ->action(function (CrewSeason $record): void {
                        $record->update(['phase' => 2]);
                        Notification::make()
                            ->title('Phase advanced')
                            ->body("Season {$record->number} is now in Phase 2 (Defend).")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (CrewSeason $record): bool => $record->active && $record->phase === 1),

                Action::make('end_and_advance')
                    ->label('End & Start New')
                    ->icon(Heroicon::OutlinedForward)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('End Season & Start New')
                    ->modalDescription('This will end the current crew season and start a new one at Phase 1.')
                    ->form([
                        Forms\Components\TextInput::make('duration_days')
                            ->label('New Season Duration (days)')
                            ->numeric()
                            ->minValue(1)
                            ->default(30)
                            ->required(),
                    ])
                    ->action(function (CrewSeason $record, array $data): void {
                        $record->update([
                            'active'   => false,
                            'ended_at' => now(),
                        ]);

                        CrewSeason::create([
                            'number'     => $record->number + 1,
                            'active'     => true,
                            'phase'      => 1,
                            'started_at' => now(),
                            'ended_at'   => now()->addDays((int) $data['duration_days']),
                        ]);

                        Notification::make()
                            ->title('Season advanced')
                            ->body("Crew Season {$record->number} ended. Season " . ($record->number + 1) . " started.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (CrewSeason $record): bool => (bool) $record->active),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCrewSeasons::route('/'),
            'create' => Pages\CreateCrewSeason::route('/create'),
            'edit'   => Pages\EditCrewSeason::route('/{record}/edit'),
        ];
    }
}