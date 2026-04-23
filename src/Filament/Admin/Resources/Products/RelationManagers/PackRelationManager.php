<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Products\RelationManagers;

use App\Models\Node;
use Fywolf\Billing\Filament\Admin\Resources\Packs\PackResource;
use Fywolf\Billing\Models\Pack;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class PackRelationManager extends RelationManager
{
    protected static string $relationship = 'packs';

    public function form(Schema $schema): Schema
    {
        return PackResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('force_out_of_stock')
                    ->label('Forced OOS')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseIcon('tabler-check')
                    ->falseColor('success'),
                TextColumn::make('stock')
                    ->label('Stock')
                    ->placeholder('Unlimited')
                    ->formatStateUsing(function (Pack $pack) {
                        if ($pack->stock === null) return null;
                        $available = $pack->availableStock();
                        return $available . ' / ' . $pack->stock;
                    }),
                TextColumn::make('cores')
                    ->formatStateUsing(fn ($state) => $state . ($state === 1 ? ' core' : ' cores')),
                TextColumn::make('memory')
                    ->formatStateUsing(fn ($state) => $state === 0 ? 'Unlimited' : Number::format($state / 1024, 2) . ' GB'),
                TextColumn::make('disk')
                    ->formatStateUsing(fn ($state) => $state === 0 ? 'Unlimited' : Number::format($state / 1024, 2) . ' GB'),
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->label('Add Pack')
                    ->createAnother(false),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('tabler-edit')
                    ->url(fn (Pack $record) => PackResource::getUrl('edit', ['record' => $record])),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No Packs')
            ->emptyStateDescription('Add packs to this category.');
    }
}
