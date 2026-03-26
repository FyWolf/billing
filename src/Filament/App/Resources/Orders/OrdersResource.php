<?php

namespace Boy132\Billing\Filament\App\Resources\Orders;

use App\Filament\Components\Tables\Columns\DateTimeColumn;
use App\Filament\Server\Pages\Console;
use Boy132\Billing\Enums\OrderStatus;
use Boy132\Billing\Filament\App\Resources\Orders\Pages\ListOrders;
use Boy132\Billing\Models\Customer;
use Boy132\Billing\Models\Order;
use Boy132\Billing\Models\ProductPrice;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use NumberFormatter;

class OrdersResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-truck-delivery';

    public static function getEloquentQuery(): Builder
    {
        /** @var Customer $customer */
        $customer = Customer::firstOrCreate([
            'user_id' => user()->id,
        ], [
            'first_name' => user()->username,
            'last_name' => user()->username,
        ]);

        return parent::getEloquentQuery()->where('customer_id', $customer->id);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count() ?: null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->sortable()
                    ->badge(),
                TextColumn::make('server.name')
                    ->label('Server')
                    ->placeholder('No server')
                    ->icon('tabler-brand-docker')
                    ->sortable(),
                TextColumn::make('productPrice.product.name')
                    ->label('Product')
                    ->icon('tabler-package')
                    ->sortable(),
                TextColumn::make('productPrice.name')
                    ->label('Price')
                    ->sortable(),
                TextColumn::make('productPrice.cost')
                    ->label('Cost')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        $formatter = new NumberFormatter(user()->language, NumberFormatter::CURRENCY);

                        return $formatter->formatCurrency($state, config('billing.currency'));
                    }),
                DateTimeColumn::make('expires_at')
                    ->label('Expires')
                    ->placeholder('No expire')
                    ->color(fn ($state) => $state <= now('UTC') ? 'danger' : null)
                    ->since(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->hidden(fn (Order $order) => !$order->server)
                    ->url(fn (Order $order) => Console::getUrl(panel: 'server', tenant: $order->server)),
                Action::make('change_plan')
                    ->label('Change Plan')
                    ->icon('tabler-arrows-exchange')
                    ->visible(fn (Order $order) => $order->status === OrderStatus::Active && $order->server)
                    ->color('info')
                    ->form(fn (Order $order) => [
                        Select::make('new_price_id')
                            ->label('New Plan')
                            ->options(function () use ($order) {
                                $currentPrice = $order->productPrice;

                                return ProductPrice::where('product_id', $currentPrice->product_id)
                                    ->where('id', '!=', $currentPrice->id)
                                    ->get()
                                    ->mapWithKeys(fn (ProductPrice $price) => [
                                        $price->id => $price->name . ' — ' . $price->formatCost() . ($price->cost > $currentPrice->cost ? ' (upgrade)' : ' (downgrade)'),
                                    ]);
                            })
                            ->required()
                            ->searchable(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Change Plan')
                    ->modalDescription('Your server\'s startup variables will be updated immediately. The new price takes effect on your next renewal.')
                    ->action(function (Order $order, array $data) {
                        $newPrice = ProductPrice::findOrFail($data['new_price_id']);

                        $order->changePlan($newPrice);

                        Notification::make()
                            ->title('Plan changed')
                            ->body("Switched to {$newPrice->name}. Server variables have been updated.")
                            ->success()
                            ->send();
                    }),
                Action::make('activate')
                    ->visible(fn (Order $order) => $order->status === OrderStatus::Pending)
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Order $order) => redirect($order->getPaymentUrl())),
                Action::make('cancel')
                    ->visible(fn (Order $order) => $order->status === OrderStatus::Pending || $order->status === OrderStatus::Active)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Order $order) => $order->close()),
                Action::make('renew')
                    ->visible(fn (Order $order) => $order->status === OrderStatus::Expired && $order->productPrice->renewable)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (Order $order) => redirect($order->getPaymentUrl())),
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
