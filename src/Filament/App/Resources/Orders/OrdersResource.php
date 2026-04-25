<?php

namespace Fywolf\Billing\Filament\App\Resources\Orders;

use App\Filament\Components\Tables\Columns\DateTimeColumn;
use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Filament\App\Resources\Orders\Pages\ListOrders;
use Fywolf\Billing\Models\Customer;
use Fywolf\Billing\Models\Order;
use Fywolf\Billing\Models\PackPrice;
use Fywolf\Billing\ProvisionerRegistry;
use Filament\Actions\Action;
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

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function getEloquentQuery(): Builder
    {
        $customer = Customer::where('user_id', user()->id)->first();

        if (!$customer) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

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
                TextColumn::make('packPrice.pack.name')
                    ->label('Pack')
                    ->icon('tabler-package')
                    ->sortable(),
                TextColumn::make('packPrice.name')
                    ->label('Price')
                    ->sortable(),
                TextColumn::make('packPrice.cost')
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
                Action::make('invoice')
                    ->label('Invoice')
                    ->icon('tabler-file-invoice')
                    ->color('gray')
                    ->visible(fn () => class_exists(\Barryvdh\DomPDF\ServiceProvider::class))
                    ->url(fn (Order $order) => route('billing.invoices.user', $order))
                    ->openUrlInNewTab(),
                Action::make('manage')
                    ->label('Manage')
                    ->icon('tabler-settings')
                    ->color('gray')
                    ->hidden(fn (Order $order) => app(ProvisionerRegistry::class)
                        ->get($order->packPrice->pack->provisioner ?? 'wings')
                        ->getManagementUrl($order) === null)
                    ->url(fn (Order $order) => app(ProvisionerRegistry::class)
                        ->get($order->packPrice->pack->provisioner ?? 'wings')
                        ->getManagementUrl($order))
                    ->openUrlInNewTab(),
                Action::make('change_plan')
                    ->label('Change Plan')
                    ->icon('tabler-arrows-exchange')
                    ->visible(fn (Order $order) => $order->status === OrderStatus::Active && $order->server)
                    ->color('info')
                    ->form(fn (Order $order) => [
                        Select::make('new_price_id')
                            ->label('New Plan')
                            ->options(function () use ($order) {
                                $currentPrice = $order->packPrice;

                                return PackPrice::where('pack_id', $currentPrice->pack_id)
                                    ->where('id', '!=', $currentPrice->id)
                                    ->where('renewable', $currentPrice->renewable)
                                    ->get()
                                    ->mapWithKeys(fn (PackPrice $price) => [
                                        $price->id => $price->name . ' — ' . $price->formatCost()
                                            . ($price->cost > $currentPrice->cost ? ' (upgrade)' : ' (downgrade)'),
                                    ]);
                            })
                            ->required()
                            ->searchable(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Change Plan')
                    ->modalDescription(fn (Order $order) => $order->stripe_subscription_id
                        ? 'Your server\'s startup variables will be updated immediately. The new billing rate takes effect at your next renewal — you won\'t be charged twice for the current period.'
                        : 'Your server\'s startup variables will be updated immediately.')
                    ->action(function (Order $order, array $data) {
                        $newPrice = PackPrice::findOrFail($data['new_price_id']);

                        $order->changePlan($newPrice);

                        Notification::make()
                            ->title($order->stripe_subscription_id ? 'Plan change scheduled' : 'Plan changed')
                            ->body($order->stripe_subscription_id
                                ? "Switching to {$newPrice->name} at your next renewal."
                                : "Switched to {$newPrice->name}.")
                            ->success()
                            ->send();
                    }),
                Action::make('activate')
                    ->visible(fn (Order $order) => $order->status === OrderStatus::Pending
                        && $order->payment_gateway !== 'manual')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Order $order) => redirect($order->getPaymentUrl())),
                Action::make('cancel')
                    ->label('Cancel Subscription')
                    ->visible(fn (Order $order) =>
                        $order->status === OrderStatus::Pending ||
                        ($order->status === OrderStatus::Active && $order->stripe_subscription_id)
                    )
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Order $order) => $order->stripe_subscription_id
                        ? 'Your subscription will be cancelled at the end of the current billing period (' . ($order->expires_at?->format('M j, Y') ?? 'unknown') . '). Your server will remain active until then.'
                        : 'Are you sure you want to cancel this order?')
                    ->action(fn (Order $order) => $order->stripe_subscription_id
                        ? $order->cancelSubscription()
                        : $order->close()),
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
