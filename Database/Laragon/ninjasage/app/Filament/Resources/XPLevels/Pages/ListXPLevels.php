<?php

namespace App\Filament\Resources\XPLevels\Pages;

use App\Filament\Resources\XPLevels\XPLevelResource;
use Filament\Resources\Pages\ListRecords;

class ListXPLevels extends ListRecords
{
    protected static string $resource = XPLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
