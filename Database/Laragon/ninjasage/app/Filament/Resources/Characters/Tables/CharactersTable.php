<?php

namespace App\Filament\Resources\Characters\Tables;

use App\Filament\Resources\Characters\CharacterResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CharactersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.username')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('level')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rank')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Genin' => 'gray',
                        'Chunin' => 'warning',
                        'Jounin' => 'success',
                        'Sannin' => 'danger',
                        'Kage' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('gold')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('inventory')
                    ->label('Inventory')
                    ->icon('heroicon-o-briefcase')
                    ->color('info')
                    ->url(fn ($record) => CharacterResource::getUrl('edit', ['record' => $record])),
                Action::make('skills')
                    ->label('Skills')
                    ->icon('heroicon-o-bolt')
                    ->color('success')
                    ->url(fn ($record) => CharacterResource::getUrl('edit', ['record' => $record])),
                Action::make('pets')
                    ->label('Pets')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->url(fn ($record) => CharacterResource::getUrl('edit', ['record' => $record])),
            ])
            ->toolbarActions([
                //
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}