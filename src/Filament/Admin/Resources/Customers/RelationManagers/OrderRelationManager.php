<?php

namespace Boy132\Billing\Filament\Admin\Resources\Customers\RelationManagers;

use App\Filament\Admin\Resources\Servers\Pages\EditServer;
use App\Filament\Components\Tables\Columns\DateTimeColumn;
use Boy132\Billing\Enums\OrderStatus;
use Boy132\Billing\Filament\Admin\Resources\Products\Pages\EditProduct;
use Boy132\Billing\Models\Customer;
use Boy132\Billing\Models\Order;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use NumberFormatter;

/**
 * @method Customer getOwnerRecord()
 */
class OrderRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('status')
                    ->sortable()
                    ->badge(),
                TextColumn::make('server.name')
                    ->label('Server')
                    ->placeholder('No server')
                    ->icon('tabler-brand-docker')
                    ->sortable()
                    ->url(fn (Order $order): ?string => $order->server ? EditServer::getUrl(['record' => $order->server]) : null),
                TextColumn::make('productPrice.product.name')
                    ->label('Product')
                    ->icon('tabler-package')
                    ->sortable()
                    ->url(fn (Order $order): string => EditProduct::getUrl(['record' => $order->productPrice->product])),
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
                Action::make('activate')
                    ->visible(fn (Order $order) => $order->status !== OrderStatus::Active)
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Order $order) {
                        $order->activate(null);

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
                    ->visible(fn (Order $order) => $order->status === OrderStatus::Active)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Order $order) {
                        $order->close();

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
}
