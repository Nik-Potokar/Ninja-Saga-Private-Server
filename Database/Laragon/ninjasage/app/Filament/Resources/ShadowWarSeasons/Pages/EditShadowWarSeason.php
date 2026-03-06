<?php

namespace App\Filament\Resources\ShadowWarSeasons\Pages;

use App\Filament\Resources\ShadowWarSeasons\ShadowWarSeasonResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShadowWarSeason extends EditRecord
{
    protected static string $resource = ShadowWarSeasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
