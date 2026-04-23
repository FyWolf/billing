<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Packs\RelationManagers;

use Fywolf\Billing\Models\Expansion;
use Fywolf\Billing\Models\PackExpansion;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PackExpansionRelationManager extends RelationManager
{
    protected static string $relationship = 'packExpansions';

    protected static ?string $title = 'Available Expansions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('expansion_id')
                    ->label('Expansion')
                    ->required()
                    ->options(fn () => Expansion::where('is_enabled', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->columnSpanFull(),
                TextInput::make('custom_price')
                    ->label('Custom Price Override')
                    ->numeric()
                    ->minValue(0)
                    ->suffix(config('billing.currency'))
                    ->nullable()
                    ->placeholder('Use expansion default price')
                    ->helperText('Override the expansion base price for this pack only. Leave empty to use the expansion\'s default.'),
                Toggle::make('is_enabled')
                    ->label('Available for purchase')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('expansion.name')
                    ->label('Expansion')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('expansion.boostSummary')
                    ->label('Boosts')
                    ->state(fn (PackExpansion $record) => $record->expansion->boostSummary()),
                TextColumn::make('effective_price')
                    ->label('Price')
                    ->state(fn (PackExpansion $record) => $record->effectivePrice() . ' ' . config('billing.currency'))
                    ->description(fn (PackExpansion $record) => $record->custom_price !== null ? 'Custom price' : 'Default price'),
                IconColumn::make('is_enabled')
                    ->label('Available')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Expansion')
                    ->createAnother(false),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No Expansions')
            ->emptyStateDescription('No expansions are configured for this pack.');
    }
}
