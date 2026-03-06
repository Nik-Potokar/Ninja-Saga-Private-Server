<?php

namespace App\Filament\Resources\ClanSeasons\Pages;

use App\Filament\Resources\ClanSeasons\ClanSeasonResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClanSeason extends EditRecord
{
    protected static string $resource = ClanSeasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
