<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Orders;

use App\Filament\Admin\Resources\Servers\Pages\EditServer;
use App\Filament\Components\Tables\Columns\DateTimeColumn;
use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Enums\PaymentGateway;
use Fywolf\Billing\Filament\Admin\Resources\Customers\Pages\EditCustomer;
use Fywolf\Billing\Filament\Admin\Resources\Orders\Pages\ListOrders;
use Fywolf\Billing\Filament\Admin\Resources\Products\Pages\EditProduct;
use Fywolf\Billing\Models\AuditLog;
use Fywolf\Billing\Models\Customer;
use Fywolf\Billing\Models\Order;
use Fywolf\Billing\Models\Product;
use Fywolf\Billing\Models\ProductPrice;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use NumberFormatter;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-truck-delivery';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count() ?: null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->label('Customer')
                    ->required()
                    ->selectablePlaceholder(false)
                    ->relationship('customer')
                    ->getOptionLabelFromRecordUsing(fn (Customer $customer) => $customer->getLabel())
                    ->preload(),
                Select::make('product_price_id')
                    ->label('Product')
                    ->required()
                    ->selectablePlaceholder(false)
                    ->relationship('productPrice')
                    ->getOptionLabelFromRecordUsing(fn (ProductPrice $productPrice) => $productPrice->product->getLabel() . ' (' . $productPrice->getLabel() . ')')
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('status')
                    ->sortable()
                    ->badge()
                    ->visible(fn ($livewire) => $livewire->activeTab === 'all'),
                TextColumn::make('payment_gateway')
                    ->label('Gateway')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? PaymentGateway::tryFrom($state)?->getLabel() ?? $state : '—')
                    ->color(fn (?string $state) => $state ? PaymentGateway::tryFrom($state)?->getColor() ?? 'gray' : 'gray')
                    ->sortable(),
                TextColumn::make('customer')
                    ->label('Customer')
                    ->icon('tabler-user-dollar')
                    ->sortable()
                    ->url(fn (Order $order) => EditCustomer::getUrl(['record' => $order->customer])),
                TextColumn::make('server.name')
                    ->label('Server')
                    ->placeholder('No server')
                    ->icon('tabler-brand-docker')
                    ->sortable()
                    ->url(fn (Order $order) => $order->server ? EditServer::getUrl(['record' => $order->server]) : null),
                TextColumn::make('productPrice.product.name')
                    ->label('Product')
                    ->icon('tabler-package')
                    ->sortable()
                    ->url(fn (Order $order) => EditProduct::getUrl(['record' => $order->productPrice->product])),
                TextColumn::make('productPrice.name')
                    ->label('Price')
                    ->sortable(),
                TextColumn::make('productPrice.cost')
                    ->label('Cost')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        $formatter = new NumberFormatter(auth()->user()->language, NumberFormatter::CURRENCY);
                        return $formatter->formatCurrency($state, config('billing.currency'));
                    }),
                DateTimeColumn::make('expires_at')
                    ->label('Expires')
                    ->placeholder('No expire')
                    ->color(fn ($state) => $state <= now('UTC') ? 'danger' : null)
                    ->since(),
            ])
            ->recordActions([
                Action::make('change_plan')
                    ->label('Change Plan')
                    ->icon('tabler-arrows-exchange')
                    ->visible(fn (Order $order) => $order->status === OrderStatus::Active)
                    ->color('info')
                    ->form(fn (Order $order) => [
                        Select::make('new_price_id')
                            ->label('New Plan')
                            ->options(function () use ($order) {
                                $currentPrice = $order->productPrice;

                                // Show all prices from the same product, excluding the current one
                                return ProductPrice::where('product_id', $currentPrice->product_id)
                                    ->where('id', '!=', $currentPrice->id)
                                    ->get()
                                    ->mapWithKeys(fn (ProductPrice $price) => [
                                        $price->id => $price->name . ' — ' . $price->formatCost()
                                            . ($price->cost > $currentPrice->cost ? ' (upgrade)' : ' (downgrade)'),
                                    ]);
                            })
                            ->required()
                            ->searchable(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Change Plan')
                    ->modalDescription('The server\'s startup variables will be updated immediately.')
                    ->action(function (Order $order, array $data) {
                        $newPrice = ProductPrice::findOrFail($data['new_price_id']);

                        $order->changePlan($newPrice);

                        AuditLog::record('admin_order_plan_changed', [
                            'admin_id'       => auth()->id(),
                            'new_price_id'   => $newPrice->id,
                            'new_price_name' => $newPrice->name,
                        ], $order);

                        Notification::make()
                            ->title('Plan changed')
                            ->body("Switched {$order->getLabel()} to {$newPrice->name}")
                            ->success()
                            ->send();
                    }),
                Action::make('activate')
                    ->visible(fn (Order $order) => $order->status !== OrderStatus::Active)
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Order $order) {
                        $order->activate(null);

                        AuditLog::record('admin_order_activated', [
                            'admin_id' => auth()->id(),
                        ], $order);

                        Notification::make()
                            ->title('Order activated')
                            ->body($order->getLabel())
                            ->success()
                            ->send();
                    }),
                Action::make('create_server')
                    ->visible(fn (Order $order) => $order->status === OrderStatus::Active && !$order->server)
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function (Order $order) {
                        try {
                            $order->createServer();

                            Notification::make()
                                ->title('Server created')
                                ->body($order->getLabel())
                                ->success()
                                ->send();
                        } catch (Exception $exception) {
                            Notification::make()
                                ->title('Could not create server')
                                ->body($exception->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
                Action::make('close')
                    ->label(fn (Order $order) => $order->stripe_subscription_id ? 'Cancel Subscription' : 'Close')
                    ->visible(fn (Order $order) => in_array($order->status, [OrderStatus::Active, OrderStatus::GracePeriod]))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Order $order) {
                        $order->stripe_subscription_id
                            ? $order->cancelSubscription()
                            : $order->close();

                        AuditLog::record('admin_order_closed', [
                            'admin_id' => auth()->id(),
                        ], $order);

                        Notification::make()
                            ->title('Order closed')
                            ->body($order->getLabel())
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No Orders')
            ->emptyStateDescription('')
            ->emptyStateIcon('tabler-truck-delivery');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
        ];
    }
}
