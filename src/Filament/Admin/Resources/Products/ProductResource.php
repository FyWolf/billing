<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Products;

use App\Models\Node;
use Fywolf\Billing\Filament\Admin\Resources\Products\Pages\CreateProduct;
use Fywolf\Billing\Filament\Admin\Resources\Products\Pages\EditProduct;
use Fywolf\Billing\Filament\Admin\Resources\Products\Pages\ListProducts;
use Fywolf\Billing\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-package';

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
                    ->required(),
                Textarea::make('description')
                    ->required()
                    ->autosize(),
                TextInput::make('category')
                    ->placeholder('e.g. Minecraft, VPS, Web Hosting')
                    ->helperText('Products with the same category are grouped together on the store page.'),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers appear first within a category.'),
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
                        TextInput::make('cpu')
                            ->prefixIcon('tabler-cpu')
                            ->label('CPU')
                            ->required()
                            ->suffix('%')
                            ->numeric()
                            ->minValue(0)
                            ->hint('Set to 0 for unlimited.'),
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
                            ->default(500)
                            ->hint('Docker block I/O weight (10–1000). Higher = more I/O priority. Game servers typically need 500+.'),
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
                            ->helperText('Select which nodes this product can deploy to. If empty, auto-deployment via tags is used instead.'),
                        TagsInput::make('ports')
                            ->helperText('Port(s) the server needs (e.g. 25565). Used to find a matching allocation on the selected node(s).'),
                        TagsInput::make('tags')
                            ->default(array_filter(explode(',', config('billing.deployment_tags', ''))))
                            ->helperText('Only used when no specific nodes are selected. Must match tags on your nodes.'),
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
                    ->description(fn (Product $product) => $product->description)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('category')
                    ->placeholder('Uncategorized')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('egg.name')
                    ->sortable()
                    ->icon('tabler-egg')
                    ->url(fn (Product $product): string => route('filament.admin.resources.eggs.edit', ['record' => $product->egg])),
                TextColumn::make('cpu')
                    ->label('CPU')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => $state === 0 ? 'Unlimited' : $state . ' %'),
                TextColumn::make('memory')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => $state === 0 ? 'Unlimited' : Number::format($state / (config('panel.use_binary_prefix') ? 1024 : 1000), 2, locale: auth()->user()->language) . (config('panel.use_binary_prefix') ? ' GiB' : ' GB')),
                TextColumn::make('disk')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => $state === 0 ? 'Unlimited' : Number::format($state / (config('panel.use_binary_prefix') ? 1024 : 1000), 2, locale: auth()->user()->language) . (config('panel.use_binary_prefix') ? ' GiB' : ' GB')),
                TextColumn::make('io_weight')
                    ->label('I/O')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No Products')
            ->emptyStateDescription('')
            ->emptyStateIcon('tabler-package');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit'   => EditProduct::route('/{record}/edit'),
        ];
    }
}
