<?php

namespace App\Filament\Resources\Items\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;

use Filament\Tables\Filters\SelectFilter;

class ItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item_id')
                    ->searchable(),
                ImageColumn::make('icon'),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('level')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('price_gold')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('price_tokens')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('premium')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'weapon' => 'danger',
                        'back' => 'warning',
                        'accessory' => 'info',
                        'set' => 'success',
                        'hair' => 'primary',
                        'material' => 'gray',
                        'item' => 'secondary',
                        'essential' => 'gray',
                        default => 'gray',
                    })
                    ->searchable(),
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
                SelectFilter::make('category')
                    ->options([
                        'weapon' => 'Weapon',
                        'back' => 'Back Item',
                        'accessory' => 'Accessory',
                        'set' => 'Set/Clothing',
                        'hair' => 'Hairstyle',
                        'material' => 'Material',
                        'item' => 'Consumable Item',
                        'essential' => 'Essential',
                    ]),
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
