<?php

namespace App\Filament\Resources\Characters;

use App\Filament\Resources\Characters\Pages\CreateCharacter;
use App\Filament\Resources\Characters\Pages\EditCharacter;
use App\Filament\Resources\Characters\Pages\ListCharacters;
use App\Filament\Resources\Characters\RelationManagers;
use App\Filament\Resources\Characters\Schemas\CharacterForm;
use App\Filament\Resources\Characters\Tables\CharactersTable;
use App\Models\Character;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CharacterResource extends Resource
{
    protected static ?string $model = Character::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CharacterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CharactersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\WeaponsRelationManager::class,
            RelationManagers\ClothingRelationManager::class,
            RelationManagers\BackItemsRelationManager::class,
            RelationManagers\AccessoriesRelationManager::class,
            RelationManagers\HairstylesRelationManager::class,
            RelationManagers\ConsumablesRelationManager::class,
            RelationManagers\MaterialsRelationManager::class,
            RelationManagers\EssentialsRelationManager::class,
            RelationManagers\SkillsRelationManager::class,
            RelationManagers\PetsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCharacters::route('/'),
            'create' => CreateCharacter::route('/create'),
            'edit' => EditCharacter::route('/{record}/edit'),
        ];
    }
}