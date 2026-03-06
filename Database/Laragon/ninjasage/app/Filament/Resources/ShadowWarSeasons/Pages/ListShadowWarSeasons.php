<?php

namespace App\Filament\Resources\ShadowWarSeasons\Pages;

use App\Filament\Resources\ShadowWarSeasons\ShadowWarSeasonResource;
use App\Models\ShadowWarSeason;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListShadowWarSeasons extends ListRecords
{
    protected static string $resource = ShadowWarSeasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('start_season')
                ->label('Start New Season')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Start New Shadow War Season')
                ->form([
                    Forms\Components\TextInput::make('duration_days')
                        ->label('Duration (days)')
                        ->numeric()
                        ->minValue(1)
                        ->default(30)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    ShadowWarSeason::where('active', true)->update([
                        'active' => false,
                        'end_at' => now(),
                    ]);

                    $num = (ShadowWarSeason::max('num') ?? 0) + 1;

                    ShadowWarSeason::create([
                        'num'      => $num,
                        'active'   => true,
                        'start_at' => now(),
                        'end_at'   => now()->addDays((int) $data['duration_days']),
                    ]);

                    Notification::make()
                        ->title('Season started')
                        ->body("Shadow War Season {$num} is now active.")
                        ->success()
                        ->send();
                }),

            CreateAction::make()->label('Create Season (Manual)'),
        ];
    }
}
