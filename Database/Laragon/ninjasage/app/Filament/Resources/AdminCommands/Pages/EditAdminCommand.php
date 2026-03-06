<?php

namespace App\Filament\Resources\AdminCommands\Pages;

use App\Filament\Resources\AdminCommands\AdminCommandResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdminCommand extends EditRecord
{
    protected static string $resource = AdminCommandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
