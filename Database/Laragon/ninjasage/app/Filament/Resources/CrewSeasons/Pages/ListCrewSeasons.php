<?php

namespace App\Filament\Resources\CrewSeasons\Pages;

use App\Filament\Resources\CrewSeasons\CrewSeasonResource;
use App\Models\CrewSeason;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCrewSeasons extends ListRecords
{
    protected static string $resource = CrewSeasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('start_season')
                ->label('Start New Season')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Start New Crew Season')
                ->form([
                    Forms\Components\TextInput::make('duration_days')
                        ->label('Duration (days)')
                        ->numeric()
                        ->minValue(1)
                        ->default(30)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    CrewSeason::where('active', true)->update([
                        'active'   => false,
                        'ended_at' => now(),
                    ]);

                    $number = (CrewSeason::max('number') ?? 0) + 1;

                    CrewSeason::create([
                        'number'     => $number,
                        'active'     => true,
                        'phase'      => 1,
                        'started_at' => now(),
                        'ended_at'   => now()->addDays((int) $data['duration_days']),
                    ]);

                    Notification::make()
                        ->title('Season started')
                        ->body("Crew Season {$number} is now active at Phase 1.")
                        ->success()
                        ->send();
                }),

            CreateAction::make()->label('Create Season (Manual)'),
        ];
    }
}
