<?php

namespace App\Filament\Resources\Characters\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Support\Icons\Heroicon;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;

class BackItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $recordTitleAttribute = 'item_id';
    protected static ?string $title = 'Back Items';
    protected static string|BackedEnum|null $icon = Heroicon::OutlinedShieldCheck;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Hidden::make('category')->default('back'),
                Forms\Components\Select::make('item_id')
                    ->label('Back Item')
                    ->options(function (RelationManager $livewire) {
                        $char = $livewire->getOwnerRecord();
                        $gender = $char->gender;
                        
                        return \App\Models\Item::query()
                            ->where('item_id', 'like', 'back\_%')
                            ->where(function ($query) use ($gender) {
                                if ($gender == 0) $query->where('item_id', 'not like', '%\_1');
                                else $query->where('item_id', 'not like', '%\_0');
                            })
                            ->pluck('name', 'item_id');
                    })
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('quantity')->numeric()->default(1)->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('item_id', 'like', 'back\_%'))
            ->columns([
                Tables\Columns\TextColumn::make('item_id')->label('ID')->searchable(),
                Tables\Columns\TextColumn::make('item.name')->label('Name')->searchable(),
                Tables\Columns\TextColumn::make('quantity')->numeric(),
            ])
            ->headerActions([ CreateAction::make(), ])
            ->recordActions([ EditAction::make(), DeleteAction::make(), ])
            ->toolbarActions([ BulkActionGroup::make([ DeleteBulkAction::make(), ]), ]);
    }
}
