<?php

namespace Boy132\Billing\Filament\Admin\Resources\Products\RelationManagers;

use Boy132\Billing\Enums\PriceInterval;
use Boy132\Billing\Models\Product;
use Boy132\Billing\Models\ProductPrice;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * @method Product getOwnerRecord()
 */
class PriceRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    public function form(Schema $schema): Schema
    {
        /** @var Product $product */
        $product = $this->getOwnerRecord();
        $egg = $product->egg;

        $variableOptions = [];
        if ($egg) {
            foreach ($egg->variables as $variable) {
                $variableOptions[$variable->env_variable] = "{$variable->name} ({$variable->env_variable})";
            }
        }

        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->label('Internal Name')
                    ->columnSpanFull(),
                TextInput::make('cost')
                    ->required()
                    ->suffix(config('billing.currency'))
                    ->numeric()
                    ->minValue(0),
                Toggle::make('renewable')
                    ->label('Can be renewed?')
                    ->inline(false),
                TextInput::make('trial_days')
                    ->label('Free Trial (days)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->helperText('0 = no trial. First purchase only.'),
                Select::make('interval_type')
                    ->required()
                    ->selectablePlaceholder(false)
                    ->options(PriceInterval::class),
                TextInput::make('interval_value')
                    ->required()
                    ->numeric()
                    ->minValue(1),
                Fieldset::make('Startup Variable Overrides')
                    ->columnSpanFull()
                    ->visible(fn () => !empty($variableOptions))
                    ->schema([
                        Repeater::make('environment_overrides')
                            ->label('')
                            ->schema([
                                Select::make('variable')
                                    ->label('Variable')
                                    ->options($variableOptions)
                                    ->required()
                                    ->searchable(),
                                TextInput::make('value')
                                    ->label('Locked Value')
                                    ->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add Override')
                            ->columnSpanFull()
                            ->helperText('Lock specific startup variables for this price tier. For example, set MAX_PLAYERS to 20 for a basic plan.'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Internal Name')
                    ->sortable(),
                TextColumn::make('cost')
                    ->sortable()
                    ->state(fn (ProductPrice $price) => $price->formatCost()),
                IconColumn::make('renewable')
                    ->label('Can be renewed?')
                    ->boolean(),
                TextColumn::make('trial_days')
                    ->label('Trial')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "{$state}d" : '—')
                    ->sortable(),
                TextColumn::make('interval')
                    ->state(fn (ProductPrice $price) => $price->interval_value . ' ' . $price->interval_type->name),
                TextColumn::make('environment_overrides')
                    ->label('Overrides')
                    ->formatStateUsing(function ($state, ProductPrice $record) {
                        $overrides = $record->environment_overrides;
                        if (!$overrides || !is_array($overrides)) return '—';
                        return count($overrides) . ' var(s)';
                    })
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Create Price')
                    ->createAnother(false),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No Prices')
            ->emptyStateDescription('');
    }
}
