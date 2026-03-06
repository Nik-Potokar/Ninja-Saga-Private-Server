<?php

namespace App\Filament\Resources\ClanSeasons\Pages;

use App\Filament\Resources\ClanSeasons\ClanSeasonResource;
use App\Models\ClanSeason;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListClanSeasons extends ListRecords
{
    protected static string $resource = ClanSeasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('start_season')
                ->label('Start New Season')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Start New Clan Season')
                ->form([
                    Forms\Components\TextInput::make('duration_days')
                        ->label('Duration (days)')
                        ->numeric()
                        ->minValue(1)
                        ->default(30)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    // Deactivate any existing active season
                    ClanSeason::where('active', true)->update([
                        'active'   => false,
                        'ended_at' => now(),
                    ]);

                    $number = (ClanSeason::max('number') ?? 0) + 1;

                    ClanSeason::create([
                        'number'     => $number,
                        'active'     => true,
                        'started_at' => now(),
                        'ended_at'   => now()->addDays((int) $data['duration_days']),
                    ]);

                    Notification::make()
                        ->title('Season started')
                        ->body("Clan Season {$number} is now active.")
                        ->success()
                        ->send();
                }),

            CreateAction::make()->label('Create Season (Manual)'),
        ];
    }
}
