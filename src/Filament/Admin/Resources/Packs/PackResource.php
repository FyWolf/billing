<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Packs;

use App\Models\Node;
use Fywolf\Billing\Filament\Admin\Resources\Packs\Pages\CreatePack;
use Fywolf\Billing\Filament\Admin\Resources\Packs\Pages\EditPack;
use Fywolf\Billing\Filament\Admin\Resources\Packs\Pages\ListPacks;
use Fywolf\Billing\Filament\Admin\Resources\Packs\RelationManagers\PackExpansionRelationManager;
use Fywolf\Billing\Filament\Admin\Resources\Packs\RelationManagers\PackPriceRelationManager;
use Fywolf\Billing\Models\Pack;
use Fywolf\Billing\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class PackResource extends Resource
{
    protected static ?string $model = Pack::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-package';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('activeOrders');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count() ?: null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label('Category')
                    ->required()
                    ->options(fn () => Product::where('is_enabled', true)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->autosize(),
                FileUpload::make('image')
                    ->image()
                    ->disk('public')
                    ->directory('billing/packs')
                    ->visibility('public')
                    ->imagePreviewHeight('160')
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers appear first within the category.'),
                Toggle::make('is_enabled')
                    ->label('Enabled')
                    ->default(true),
                TextInput::make('stock')
                    ->label('Stock Limit')
                    ->numeric()
                    ->nullable()
                    ->minValue(1)
                    ->placeholder('Unlimited')
                    ->helperText('Max concurrent active orders. Leave empty for unlimited.'),
                Toggle::make('force_out_of_stock')
                    ->label('Force Out of Stock')
                    ->default(false)
                    ->helperText('Mark as out of stock without changing the actual stock number.'),
                Fieldset::make('Server')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('egg_id')
                            ->prefixIcon('tabler-egg')
                            ->label('Egg')
                            ->required()
                            ->relationship('egg', 'name')
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                        TextInput::make('cores')
                            ->prefixIcon('tabler-cpu')
                            ->label('CPU Cores')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(1),
                        TextInput::make('memory')
                            ->prefixIcon('tabler-database')
                            ->required()
                            ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB')
                            ->numeric()
                            ->minValue(0)
                            ->hint('Set to 0 for unlimited.'),
                        TextInput::make('disk')
                            ->prefixIcon('tabler-folder')
                            ->required()
                            ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB')
                            ->numeric()
                            ->minValue(0)
                            ->hint('Set to 0 for unlimited.'),
                        TextInput::make('swap')
                            ->prefixIcon('tabler-file-database')
                            ->required()
                            ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB')
                            ->numeric()
                            ->minValue(-1)
                            ->hint('Set to -1 for unlimited, 0 for no swap.'),
                        TextInput::make('io_weight')
                            ->prefixIcon('tabler-activity')
                            ->label('I/O Weight')
                            ->required()
                            ->numeric()
                            ->minValue(10)
                            ->maxValue(1000)
                            ->default(500),
                    ]),
                Fieldset::make('Deployment')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('node_ids')
                            ->label('Nodes')
                            ->multiple()
                            ->options(fn () => Node::query()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->helperText('Select specific nodes for deployment. If empty, auto-deployment via tags is used.'),
                        TagsInput::make('ports')
                            ->helperText('Port(s) the server needs (e.g. 25565).'),
                        TagsInput::make('tags')
                            ->default(array_filter(explode(',', config('billing.deployment_tags', ''))))
                            ->helperText('Only used when no specific nodes are selected.'),
                    ]),
                Fieldset::make('Limits')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextInput::make('allocation_limit')
                            ->prefixIcon('tabler-network')
                            ->required()
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('database_limit')
                            ->prefixIcon('tabler-database')
                            ->required()
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('backup_limit')
                            ->prefixIcon('tabler-copy-check')
                            ->required()
                            ->numeric()
                            ->minValue(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->description(fn (Pack $pack) => $pack->product->name ?? '—')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('product.name')
                    ->label('Category')
                    ->sortable()
                    ->searchable(),
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
                    ->formatStateUsing(function (Pack $pack) {
                        if ($pack->stock === null) return null;
                        $available = $pack->availableStock();
                        return $available . ' / ' . $pack->stock;
                    }),
                TextColumn::make('egg.name')
                    ->sortable()
                    ->icon('tabler-egg')
                    ->url(fn (Pack $pack): string => route('filament.admin.resources.eggs.edit', ['record' => $pack->egg])),
                TextColumn::make('cores')
                    ->label('Cores')
                    ->formatStateUsing(fn ($state) => $state . ($state === 1 ? ' core' : ' cores')),
                TextColumn::make('memory')
                    ->formatStateUsing(fn ($state) => $state === 0 ? 'Unlimited' : Number::format($state / (config('panel.use_binary_prefix') ? 1024 : 1000), 2, locale: auth()->user()->language) . (config('panel.use_binary_prefix') ? ' GiB' : ' GB')),
                TextColumn::make('disk')
                    ->formatStateUsing(fn ($state) => $state === 0 ? 'Unlimited' : Number::format($state / (config('panel.use_binary_prefix') ? 1024 : 1000), 2, locale: auth()->user()->language) . (config('panel.use_binary_prefix') ? ' GiB' : ' GB')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No Packs')
            ->emptyStateDescription('')
            ->emptyStateIcon('tabler-package');
    }

    public static function getRelationManagers(): array
    {
        return [
            PackPriceRelationManager::class,
            PackExpansionRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPacks::route('/'),
            'create' => CreatePack::route('/create'),
            'edit'   => EditPack::route('/{record}/edit'),
        ];
    }
}
