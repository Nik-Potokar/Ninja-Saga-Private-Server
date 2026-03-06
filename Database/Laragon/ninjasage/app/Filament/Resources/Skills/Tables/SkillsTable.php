<?php

namespace App\Filament\Resources\Skills\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;

class SkillsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('skill_id')
                    ->searchable(),
                ImageColumn::make('icon'),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('level')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('element')
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        1 => 'Wind',
                        2 => 'Fire',
                        3 => 'Lightning',
                        4 => 'Earth',
                        5 => 'Water',
                        default => 'None',
                    })
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        1 => 'success', // Greenish for Wind
                        2 => 'danger',  // Red for Fire
                        3 => 'warning', // Yellow for Lightning
                        4 => 'gray',    // Gray/Brown for Earth
                        5 => 'info',    // Blue for Water
                        default => 'gray',
                    })
                    ->sortable(),
                IconColumn::make('premium')
                    ->boolean(),
                TextColumn::make('price_gold')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('price_tokens')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}