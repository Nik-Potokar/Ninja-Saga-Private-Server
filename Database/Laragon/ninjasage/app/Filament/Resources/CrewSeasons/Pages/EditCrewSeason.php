<?php

namespace App\Filament\Resources\CrewSeasons\Pages;

use App\Filament\Resources\CrewSeasons\CrewSeasonResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCrewSeason extends EditRecord
{
    protected static string $resource = CrewSeasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
