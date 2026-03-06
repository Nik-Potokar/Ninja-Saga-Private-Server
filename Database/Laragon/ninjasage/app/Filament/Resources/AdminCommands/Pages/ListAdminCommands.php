<?php

namespace App\Filament\Resources\AdminCommands\Pages;

use App\Filament\Resources\AdminCommands\AdminCommandResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdminCommands extends ListRecords
{
    protected static string $resource = AdminCommandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
