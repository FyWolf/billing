<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Packs\RelationManagers;

use Fywolf\Billing\Enums\PriceInterval;
use Fywolf\Billing\Models\Pack;
use Fywolf\Billing\Models\PackPrice;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
 * @method Pack getOwnerRecord()
 */
class PackPriceRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    public function form(Schema $schema): Schema
    {
        /** @var Pack $pack */
        $pack = $this->getOwnerRecord();
        $egg  = $pack->egg;

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
                Fieldset::make('Resources')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextInput::make('cores')
                            ->prefixIcon('tabler-cpu')
                            ->label('CPU Cores')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(1),
                        TextInput::make('memory')
                            ->prefixIcon('tabler-database')
                            ->label('Memory')
                            ->required()
                            ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB')
                            ->numeric()
                            ->minValue(0)
                            ->default(1024)
                            ->hint('0 = unlimited'),
                        TextInput::make('disk')
                            ->prefixIcon('tabler-folder')
                            ->label('Disk')
                            ->required()
                            ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB')
                            ->numeric()
                            ->minValue(0)
                            ->default(5120)
                            ->hint('0 = unlimited'),
                        TextInput::make('swap')
                            ->prefixIcon('tabler-file-database')
                            ->label('Swap')
                            ->required()
                            ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB')
                            ->numeric()
                            ->minValue(-1)
                            ->default(0)
                            ->hint('-1 = unlimited, 0 = none'),
                        TextInput::make('io_weight')
                            ->prefixIcon('tabler-activity')
                            ->label('I/O Weight')
                            ->required()
                            ->numeric()
                            ->minValue(10)
                            ->maxValue(1000)
                            ->default(500),
                        TextInput::make('allocation_limit')
                            ->prefixIcon('tabler-network')
                            ->label('Allocations')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('database_limit')
                            ->prefixIcon('tabler-database')
                            ->label('Databases')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('backup_limit')
                            ->prefixIcon('tabler-copy-check')
                            ->label('Backups')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ]),
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
                            ->helperText('Lock specific startup variables for this price tier.'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->sortable(),
                TextColumn::make('specs')
                    ->label('Resources')
                    ->state(function (PackPrice $price) {
                        $mem  = $price->memory === 0 ? '∞' : round($price->memory / (config('panel.use_binary_prefix') ? 1024 : 1000), 1) . (config('panel.use_binary_prefix') ? 'GiB' : 'GB');
                        $disk = $price->disk   === 0 ? '∞' : round($price->disk   / (config('panel.use_binary_prefix') ? 1024 : 1000), 1) . (config('panel.use_binary_prefix') ? 'GiB' : 'GB');
                        return "{$price->cores} core(s) · {$mem} RAM · {$disk} Disk";
                    }),
                TextColumn::make('cost')
                    ->sortable()
                    ->state(fn (PackPrice $price) => $price->formatCost()),
                IconColumn::make('renewable')
                    ->label('Renewable')
                    ->boolean(),
                TextColumn::make('trial_days')
                    ->label('Trial')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "{$state}d" : '—')
                    ->sortable(),
                TextColumn::make('interval')
                    ->state(fn (PackPrice $price) => $price->interval_value . ' ' . $price->interval_type->name),
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
