<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Expansions;

use Fywolf\Billing\Filament\Admin\Resources\Expansions\Pages\CreateExpansion;
use Fywolf\Billing\Filament\Admin\Resources\Expansions\Pages\EditExpansion;
use Fywolf\Billing\Filament\Admin\Resources\Expansions\Pages\ListExpansions;
use Fywolf\Billing\Models\Expansion;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ExpansionResource extends Resource
{
    protected static ?string $model = Expansion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-puzzle';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count() ?: null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->autosize()
                    ->columnSpanFull(),
                FileUpload::make('image')
                    ->image()
                    ->disk('public')
                    ->directory('billing/expansions')
                    ->visibility('public')
                    ->columnSpanFull(),
                TextInput::make('cost')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->suffix(config('billing.currency'))
                    ->helperText('Base price. Can be overridden per pack.'),
                Toggle::make('is_enabled')
                    ->label('Enabled')
                    ->default(true),
                Toggle::make('force_out_of_stock')
                    ->label('Force Out of Stock')
                    ->default(false)
                    ->helperText('Mark as out of stock without changing the actual stock number.'),
                TextInput::make('stock')
                    ->label('Stock Limit')
                    ->numeric()
                    ->nullable()
                    ->minValue(1)
                    ->placeholder('Unlimited'),
                Section::make('Resource Boosts')
                    ->columnSpanFull()
                    ->columns(3)
                    ->description('These values are added on top of the pack\'s base resources when the expansion is active.')
                    ->schema([
                        TextInput::make('cores_boost')
                            ->prefixIcon('tabler-cpu')
                            ->label('CPU Cores')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('memory_boost')
                            ->prefixIcon('tabler-database')
                            ->label('Memory')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB'),
                        TextInput::make('disk_boost')
                            ->prefixIcon('tabler-folder')
                            ->label('Disk')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB'),
                        TextInput::make('swap_boost')
                            ->prefixIcon('tabler-file-database')
                            ->label('Swap')
                            ->numeric()
                            ->default(0)
                            ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB'),
                        TextInput::make('allocation_limit_boost')
                            ->prefixIcon('tabler-network')
                            ->label('Allocations')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('database_limit_boost')
                            ->prefixIcon('tabler-database')
                            ->label('Databases')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('backup_limit_boost')
                            ->prefixIcon('tabler-copy-check')
                            ->label('Backups')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('boost_summary')
                    ->label('Boosts')
                    ->state(fn (Expansion $record) => $record->boostSummary()),
                TextColumn::make('cost')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state . ' ' . config('billing.currency')),
                ToggleColumn::make('is_enabled')
                    ->label('Enabled')
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
                    ->formatStateUsing(function (Expansion $expansion) {
                        if ($expansion->stock === null) return null;
                        $available = $expansion->availableStock();
                        return $available . ' / ' . $expansion->stock;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No Expansions')
            ->emptyStateDescription('')
            ->emptyStateIcon('tabler-puzzle');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListExpansions::route('/'),
            'create' => CreateExpansion::route('/create'),
            'edit'   => EditExpansion::route('/{record}/edit'),
        ];
    }
}
