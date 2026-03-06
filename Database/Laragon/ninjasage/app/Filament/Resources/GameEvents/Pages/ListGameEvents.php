<?php

namespace App\Filament\Resources\GameEvents\Pages;

use App\Filament\Resources\GameEvents\GameEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGameEvents extends ListRecords
{
    protected static string $resource = GameEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
