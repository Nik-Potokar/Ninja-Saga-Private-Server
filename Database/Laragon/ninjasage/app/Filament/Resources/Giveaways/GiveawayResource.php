<?php

namespace App\Filament\Resources\Giveaways;

use App\Filament\Resources\Giveaways\Pages\CreateGiveaway;
use App\Filament\Resources\Giveaways\Pages\EditGiveaway;
use App\Filament\Resources\Giveaways\Pages\ListGiveaways;
use App\Filament\Resources\Giveaways\Schemas\GiveawayForm;
use App\Filament\Resources\Giveaways\Tables\GiveawaysTable;
use App\Models\Giveaway;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class GiveawayResource extends Resource
{
    protected static ?string $model = Giveaway::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return 'Giveaways';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Admin Tools';
    }

    public static function form(Schema $schema): Schema
    {
        return GiveawayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GiveawaysTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListGiveaways::route('/'),
            'create' => CreateGiveaway::route('/create'),
            'edit'   => EditGiveaway::route('/{record}/edit'),
        ];
    }
}
