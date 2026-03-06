<?php

namespace App\Filament\Resources\Mails;

use App\Filament\Resources\Mails\Pages\CreateMail;
use App\Filament\Resources\Mails\Pages\ListMails;
use App\Filament\Resources\Mails\Schemas\MailForm;
use App\Filament\Resources\Mails\Tables\MailsTable;
use App\Models\CharacterMail;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MailResource extends Resource
{
    protected static ?string $model = CharacterMail::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Mail';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Admin Tools';
    }

    public static function form(Schema $schema): Schema
    {
        return MailForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MailsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListMails::route('/'),
            'create' => CreateMail::route('/create'),
        ];
    }
}
