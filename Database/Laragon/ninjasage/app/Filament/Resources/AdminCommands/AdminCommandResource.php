<?php

namespace App\Filament\Resources\AdminCommands;

use App\Filament\Resources\AdminCommands\Pages\CreateAdminCommand;
use App\Filament\Resources\AdminCommands\Pages\EditAdminCommand;
use App\Filament\Resources\AdminCommands\Pages\ListAdminCommands;
use App\Filament\Resources\AdminCommands\Schemas\AdminCommandForm;
use App\Filament\Resources\AdminCommands\Tables\AdminCommandsTable;
use App\Models\AdminCommand;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AdminCommandResource extends Resource
{
    protected static ?string $model = AdminCommand::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-command-line';


    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Commands';
    }


    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Admin Tools';
    }

    public static function form(Schema $schema): Schema
    {
        return AdminCommandForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminCommandsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListAdminCommands::route('/'),
            'create' => CreateAdminCommand::route('/create'),
            'edit'   => EditAdminCommand::route('/{record}/edit'),
        ];
    }
}
