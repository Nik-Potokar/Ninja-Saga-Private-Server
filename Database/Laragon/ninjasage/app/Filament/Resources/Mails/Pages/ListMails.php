<?php

namespace App\Filament\Resources\Mails\Pages;

use App\Filament\Resources\Mails\MailResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMails extends ListRecords
{
    protected static string $resource = MailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Send Mail'),
        ];
    }
}
