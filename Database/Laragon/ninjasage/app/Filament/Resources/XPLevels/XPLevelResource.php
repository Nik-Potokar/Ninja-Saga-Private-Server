<?php

namespace App\Filament\Resources\XPLevels;

use App\Filament\Resources\XPLevels\Pages\EditXPLevel;
use App\Filament\Resources\XPLevels\Pages\ListXPLevels;
use App\Filament\Resources\XPLevels\Schemas\XPLevelForm;
use App\Filament\Resources\XPLevels\Tables\XPLevelsTable;
use App\Models\XP;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class XPLevelResource extends Resource
{
    protected static ?string $model = XP::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'XP Levels';

    protected static ?string $modelLabel = 'XP Level';

    protected static ?string $pluralModelLabel = 'XP Levels';

    protected static ?string $slug = 'xp-levels';

    public static function form(Schema $schema): Schema
    {
        return XPLevelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return XPLevelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListXPLevels::route('/'),
            'edit'  => EditXPLevel::route('/{record}/edit'),
        ];
    }
}
