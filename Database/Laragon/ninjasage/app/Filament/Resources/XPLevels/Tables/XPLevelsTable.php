<?php

namespace App\Filament\Resources\XPLevels\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class XPLevelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('level')
                    ->label('Level')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('character_xp')
                    ->label('Character XP Required')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('pet_xp')
                    ->label('Pet XP Required')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('level', 'asc')
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
