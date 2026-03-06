<?php

namespace App\Filament\Resources\XPLevels\Pages;

use App\Filament\Resources\XPLevels\XPLevelResource;
use Filament\Resources\Pages\EditRecord;

class EditXPLevel extends EditRecord
{
    protected static string $resource = XPLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
