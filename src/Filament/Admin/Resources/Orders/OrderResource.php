<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Orders;

use App\Filament\Admin\Resources\Servers\Pages\EditServer;
use App\Filament\Components\Tables\Columns\DateTimeColumn;
use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Enums\PaymentGateway;
use Fywolf\Billing\Filament\Admin\Resources\Customers\Pages\EditCustomer;
use Fywolf\Billing\Filament\Admin\Resources\Orders\Pages\ListOrders;
use Fywolf\Billing\Filament\Admin\Resources\Packs\Pages\EditPack;
use Fywolf\Billing\Models\AuditLog;
use Fywolf\Billing\Models\Customer;
use Fywolf\Billing\Models\Order;
use Fywolf\Billing\Models\PackPrice;
use Fywolf\Billing\ProvisionerRegistry;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                Select::make('pack_price_id')
                    ->label('Pack')
                    ->required()
                    ->selectablePlaceholder(false)
                    ->relationship('packPrice')
                    ->getOptionLabelFromRecordUsing(fn (PackPrice $packPrice) => $packPrice->pack->getLabel() . ' (' . $packPrice->getLabel() . ')')
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
                TextColumn::make('packPrice.pack.name')
                    ->label('Pack')
                    ->icon('tabler-package')
                    ->sortable()
                    ->url(fn (Order $order) => EditPack::getUrl(['record' => $order->packPrice->pack])),
                TextColumn::make('packPrice.name')
                    ->label('Price')
                    ->sortable(),
                TextColumn::make('packPrice.cost')
                    ->label('Cost')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        $formatter = new NumberFormatter(auth()->user()->language, NumberFormatter::CURRENCY);
                        return $formatter->formatCurrency($state, config('billing.currency'));
                    }),
                TextColumn::make('pendingPackPrice.name')
                    ->label('Pending Plan')
                    ->placeholder('—')
                    ->icon('tabler-clock')
                    ->color('warning')
                    ->tooltip('Plan change scheduled for next renewal'),
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
                    ->url(fn (Order $order) => route('billing.invoices.admin', $order))
                    ->openUrlInNewTab(),
                Action::make('change_plan')
                    ->label('Change Plan')
                    ->icon('tabler-arrows-exchange')
                    ->visible(fn (Order $order) => $order->status === OrderStatus::Active)
                    ->color('info')
                    ->schema(fn (Order $order) => [
                        Select::make('new_price_id')
                            ->label('New Plan')
                            ->options(function () use ($order) {
                                $currentPrice = $order->packPrice;

                                return PackPrice::where('pack_id', $currentPrice->pack_id)
                                    ->where('id', '!=', $currentPrice->id)
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
                        ? 'The server\'s startup variables will be updated immediately. The new billing rate takes effect at the next renewal.'
                        : 'The server\'s startup variables will be updated immediately.')
                    ->action(function (Order $order, array $data) {
                        $newPrice = PackPrice::findOrFail($data['new_price_id']);

                        $order->changePlan($newPrice);

                        AuditLog::record('admin_order_plan_changed', [
                            'admin_id'       => auth()->id(),
                            'new_price_id'   => $newPrice->id,
                            'new_price_name' => $newPrice->name,
                        ], $order);

                        Notification::make()
                            ->title($order->stripe_subscription_id ? 'Plan change scheduled' : 'Plan changed')
                            ->body($order->stripe_subscription_id
                                ? "Plan change to {$newPrice->name} will apply at next renewal."
                                : "Switched {$order->getLabel()} to {$newPrice->name}.")
                            ->success()
                            ->send();
                    }),
                Action::make('activate')
                    ->visible(fn (Order $order) => $order->status !== OrderStatus::Active)
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Order $order) {
                        try {
                            $order->activate(null);

                            AuditLog::record('admin_order_activated', [
                                'admin_id' => auth()->id(),
                            ], $order);

                            Notification::make()
                                ->title('Order activated')
                                ->body($order->getLabel())
                                ->success()
                                ->send();
                        } catch (Exception $exception) {
                            Notification::make()
                                ->title('Activation failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
                Action::make('create_server')
                    ->label('Provision')
                    ->visible(fn (Order $order) => $order->status === OrderStatus::Active
                        && !app(ProvisionerRegistry::class)
                            ->get($order->packPrice->pack->provisioner ?? 'wings')
                            ->isProvisioned($order))
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function (Order $order) {
                        try {
                            app(ProvisionerRegistry::class)
                                ->get($order->packPrice->pack->provisioner ?? 'wings')
                                ->provision($order);

                            Notification::make()
                                ->title('Provisioned successfully')
                                ->body($order->getLabel())
                                ->success()
                                ->send();
                        } catch (Exception $exception) {
                            Notification::make()
                                ->title('Provisioning failed')
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
                    ->modalDescription(fn (Order $order) => $order->stripe_subscription_id
                        ? 'The subscription will be cancelled at the end of the current billing period (' . ($order->expires_at?->format('M j, Y') ?? 'unknown') . '). The server will remain active until then.'
                        : null)
                    ->action(function (Order $order) {
                        $order->stripe_subscription_id
                            ? $order->cancelSubscription()
                            : $order->close();

                        AuditLog::record('admin_order_closed', [
                            'admin_id' => auth()->id(),
                        ], $order);

                        Notification::make()
                            ->title($order->stripe_subscription_id ? 'Subscription cancellation scheduled' : 'Order closed')
                            ->body($order->stripe_subscription_id
                                ? $order->getLabel() . ' will be closed on ' . ($order->expires_at?->format('M j, Y') ?? 'period end') . '.'
                                : $order->getLabel())
                            ->success()
                            ->send();
                    }),
                Action::make('refund')
                    ->label('Refund')
                    ->icon('tabler-receipt-refund')
                    ->visible(fn (Order $order) => in_array($order->status, [OrderStatus::Active, OrderStatus::Cancelled, OrderStatus::GracePeriod])
                        && ($order->stripe_payment_id || $order->stripe_subscription_id || $order->stripe_checkout_id))
                    ->color('warning')
                    ->schema(fn (Order $order) => [
                        TextInput::make('amount')
                            ->label('Refund Amount (' . strtoupper(config('billing.currency', 'USD')) . ')')
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue($order->packPrice->cost)
                            ->placeholder($order->packPrice->formatCost() . ' (full refund)')
                            ->helperText('Leave empty for a full refund.'),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Refund Order')
                    ->modalDescription(fn (Order $order) => 'This will refund the payment via Stripe, cancel any active subscription, and close the order. The server will be suspended.')
                    ->action(function (Order $order, array $data) {
                        try {
                            $amountInCents = !empty($data['amount'])
                                ? (int) round((float) $data['amount'] * 100)
                                : null;

                            $refundId = $order->refund($amountInCents);

                            Notification::make()
                                ->title('Order refunded')
                                ->body($order->getLabel() . " — Stripe refund: {$refundId}")
                                ->success()
                                ->send();
                        } catch (Exception $exception) {
                            Notification::make()
                                ->title('Refund failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
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
