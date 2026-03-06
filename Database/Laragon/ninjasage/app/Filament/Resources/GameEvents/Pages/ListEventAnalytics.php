<?php

namespace App\Filament\Resources\GameEvents\Pages;

use App\Filament\Resources\GameEvents\EventAnalyticsResource;
use Filament\Resources\Pages\ListRecords;

class ListEventAnalytics extends ListRecords
{
    protected static string $resource = EventAnalyticsResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
