<?php

namespace App\Filament\Resources\GameEvents;

use App\Models\CharacterEventData;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class EventAnalyticsResource extends Resource
{
    protected static ?string $model = CharacterEventData::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Event Analytics';

    protected static ?string $pluralLabel = 'Event Analytics';

    protected static ?string $label = 'Event Analytics';

    public static function getNavigationGroup(): ?string
    {
        return 'Game Events';
    }

    // -------------------------------------------------------------------------
    // No form — this resource is read-only analytics.
    // -------------------------------------------------------------------------

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                CharacterEventData::query()
                    ->select([
                        'event_key',
                        DB::raw('COUNT(DISTINCT character_id) AS player_count'),
                        DB::raw('SUM(battles)              AS total_battles'),
                        DB::raw('ROUND(AVG(battles), 1)    AS avg_battles'),
                        DB::raw('SUM(energy)               AS total_energy_remaining'),
                        DB::raw('SUM(CASE WHEN bought = 1 THEN 1 ELSE 0 END) AS bought_count'),
                        DB::raw('SUM(JSON_LENGTH(COALESCE(milestones_claimed, "[]"))) AS total_milestones_claimed'),
                    ])
                    ->groupBy('event_key')
            )
            ->columns([
                Tables\Columns\TextColumn::make('event_key')
                    ->label('Event Key')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('player_count')
                    ->label('Players')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_battles')
                    ->label('Total Battles')
                    ->numeric(thousandsSeparator: ',')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('avg_battles')
                    ->label('Avg Battles / Player')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_milestones_claimed')
                    ->label('Milestones Claimed')
                    ->numeric(thousandsSeparator: ',')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('bought_count')
                    ->label('Packages Bought')
                    ->numeric(thousandsSeparator: ',')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_energy_remaining')
                    ->label('Total Energy Left')
                    ->numeric(thousandsSeparator: ',')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('total_battles', 'desc')
            ->actions([
                Tables\Actions\Action::make('drill_down')
                    ->label('View Players')
                    ->icon('heroicon-o-users')
                    ->url(fn (CharacterEventData $record): string =>
                        EventAnalyticsResource::getUrl('players') . '?' . http_build_query(['event_key' => $record->event_key])
                    ),
            ])
            ->paginated(false);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'   => Pages\ListEventAnalytics::route('/'),
            'players' => Pages\ListEventPlayers::route('/players'),
        ];
    }
}
