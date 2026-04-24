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
                Toggle::make('visible_in_store')
                    ->label('Visible in Store')
                    ->default(true)
                    ->helperText('Uncheck to hide from customers. Admins can still create orders for this pack manually.'),
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
                Select::make('egg_id')
                    ->prefixIcon('tabler-egg')
                    ->label('Egg')
                    ->nullable()
                    ->relationship('egg', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->columnSpanFull()
                    ->helperText('Required for game server packs. Leave empty for VPS or other non-game products.'),
                Fieldset::make('Deployment')
                    ->columnSpanFull()
                    ->columns(2)
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => !empty($get('egg_id')))
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
                ToggleColumn::make('visible_in_store')
                    ->label('In Store')
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
                    ->placeholder('—')
                    ->url(fn (Pack $pack): ?string => $pack->egg ? route('filament.admin.resources.eggs.edit', ['record' => $pack->egg]) : null),
                TextColumn::make('prices_count')
                    ->label('Tiers')
                    ->counts('prices')
                    ->sortable(),
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
