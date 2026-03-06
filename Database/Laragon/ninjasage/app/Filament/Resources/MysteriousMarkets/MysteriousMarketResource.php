<?php

namespace App\Filament\Resources\MysteriousMarkets;

use App\Filament\Resources\MysteriousMarkets\Pages;
use App\Models\MysteriousMarket;
use BackedEnum;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class MysteriousMarketResource extends Resource
{
    protected static ?string $model = MysteriousMarket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'Mysterious Market';

    public static function getNavigationGroup(): ?string
    {
        return 'Game Events';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Market Settings')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('active')
                        ->label('Active')
                        ->helperText('Only one market should be active at a time. Activating this will show the Limited Store button in the HUD.')
                        ->required()
                        ->default(false),

                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label('Ends At')
                        ->required()
                        ->helperText('The market will automatically stop accepting new purchases after this time.'),

                    Forms\Components\TextInput::make('discount')
                        ->label('Emblem Discount %')
                        ->default('0')
                        ->maxLength(10)
                        ->helperText('Displayed as "Emblem X% OFF" in the store (cosmetic only).'),

                    Forms\Components\TextInput::make('refresh_cost')
                        ->label('Refresh Cost (Tokens)')
                        ->numeric()
                        ->default(50)
                        ->required(),

                    Forms\Components\TextInput::make('refresh_max')
                        ->label('Max Refreshes Per Player')
                        ->numeric()
                        ->default(3)
                        ->required()
                        ->helperText('How many times each player can refresh their item selection.'),
                ]),

            Section::make('Featured Items (up to 4)')
                ->description('These are the skills shown directly in the store window. Add 1–4 items. Each item has two token prices: price 1 for premium accounts, price 2 for limited accounts.')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('code')
                                ->label('Skill ID')
                                ->required()
                                ->placeholder('e.g. skill_101')
                                ->helperText('Must match the skill_id in the skills table.'),

                            Forms\Components\TextInput::make('prices.0')
                                ->label('Price 1 – Tokens (Premium / Non-Limited Accounts)')
                                ->numeric()
                                ->default(500)
                                ->required(),

                            Forms\Components\TextInput::make('prices.1')
                                ->label('Price 2 – Tokens (Limited Accounts)')
                                ->numeric()
                                ->default(800)
                                ->required(),
                        ])
                        ->columns(3)
                        ->minItems(1)
                        ->maxItems(4)
                        ->addActionLabel('Add Featured Item')
                        ->reorderableWithDragAndDrop()
                        ->collapsible(),
                ]),

            Section::make('All Packages (Full List & Refresh Pool)')
                ->description('All skills available in this market. Used for the "View All Skills" list and as the pool when a player refreshes their featured items.')
                ->schema([
                    Forms\Components\Repeater::make('all_packages')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('code')
                                ->label('Skill ID')
                                ->required()
                                ->placeholder('e.g. skill_101'),

                            Forms\Components\TextInput::make('prices.0')
                                ->label('Price 1 (Premium)')
                                ->numeric()
                                ->default(500)
                                ->required(),

                            Forms\Components\TextInput::make('prices.1')
                                ->label('Price 2 (Limited)')
                                ->numeric()
                                ->default(800)
                                ->required(),
                        ])
                        ->columns(3)
                        ->addActionLabel('Add Package')
                        ->reorderableWithDragAndDrop()
                        ->collapsible(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->label('Active')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Ends At')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('discount')
                    ->label('Discount %')
                    ->suffix('%'),

                Tables\Columns\TextColumn::make('refresh_cost')
                    ->label('Refresh Cost')
                    ->suffix(' Tokens'),

                Tables\Columns\TextColumn::make('refresh_max')
                    ->label('Max Refreshes'),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Featured Items')
                    ->getStateUsing(fn (MysteriousMarket $record) => count($record->items ?? [])),

                Tables\Columns\TextColumn::make('all_packages_count')
                    ->label('Total Packages')
                    ->getStateUsing(fn (MysteriousMarket $record) => count($record->all_packages ?? [])),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('active', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Status')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMysteriousMarkets::route('/'),
            'create' => Pages\CreateMysteriousMarket::route('/create'),
            'edit'   => Pages\EditMysteriousMarket::route('/{record}/edit'),
        ];
    }
}