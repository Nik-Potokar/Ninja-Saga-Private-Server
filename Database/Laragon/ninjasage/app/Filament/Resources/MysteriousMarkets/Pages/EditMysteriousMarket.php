<?php

namespace App\Filament\Resources\MysteriousMarkets\Pages;

use App\Filament\Resources\MysteriousMarkets\MysteriousMarketResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMysteriousMarket extends EditRecord
{
    protected static string $resource = MysteriousMarketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
