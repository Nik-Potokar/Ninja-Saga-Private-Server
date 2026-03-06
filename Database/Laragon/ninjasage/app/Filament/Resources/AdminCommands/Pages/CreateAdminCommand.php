<?php

namespace App\Filament\Resources\AdminCommands\Pages;

use App\Filament\Resources\AdminCommands\AdminCommandResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminCommand extends CreateRecord
{
    protected static string $resource = AdminCommandResource::class;
}
