<?php

namespace App\Filament\Resources\MysteriousMarkets\Pages;

use App\Filament\Resources\MysteriousMarkets\MysteriousMarketResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMysteriousMarkets extends ListRecords
{
    protected static string $resource = MysteriousMarketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
