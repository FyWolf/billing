<?php

namespace Boy132\Billing\Filament\App\Widgets;

use App\Filament\Server\Pages\Console;
use Boy132\Billing\Enums\OrderStatus;
use Boy132\Billing\Enums\PaymentGateway;
use Boy132\Billing\Models\Customer;
use Boy132\Billing\Models\Order;
use Boy132\Billing\Models\Product;
use Boy132\Billing\Services\PayPalService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Support\Number;
use RuntimeException;

class ProductWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'billing::widget'; // @phpstan-ignore property.defaultValue

    public ?Product $product = null;

    public function content(Schema $schema): Schema
    {
        $actions = [];

        foreach ($this->product->prices as $price) {
            $label = $price->getLabel();
            if ($price->hasTrial()) {
                $label .= " ({$price->trial_days}-day free trial)";
            }

            $actions[] = Action::make(str_slug($price->name))
                ->label($label)
                ->action(function () use ($price) {
                    // ----------------------------------------------------------
                    // Ensure the customer record exists
                    // ----------------------------------------------------------
                    /** @var Customer $customer */
                    $customer = Customer::firstOrCreate(
                        ['user_id' => user()->id],
                        [
                            'first_name' => user()->username,
                            'last_name'  => user()->username,
                        ]
                    );

                    // ----------------------------------------------------------
                    // Duplicate order prevention
                    // ----------------------------------------------------------
                    if (Order::hasDuplicateFor($customer->id, $price->id)) {
                        Notification::make()
                            ->title('Already subscribed')
                            ->body('You already have an active or pending order for this product.')
                            ->warning()
                            ->send();
                        return;
                    }

                    // ----------------------------------------------------------
                    // Free tier — activate immediately
                    // ----------------------------------------------------------
                    if ($price->isFree()) {
                        $price->sync();

                        /** @var Order $order */
                        $order = Order::create([
                            'customer_id'      => $customer->id,
                            'product_price_id' => $price->id,
                            'payment_gateway'  => PaymentGateway::Trial->value,
                            'status'           => OrderStatus::Pending->value,
                        ]);

                        $order->activate(null);
                        $order->refresh();

                        if ($order->server) {
                            return redirect(Console::getUrl(panel: 'server', tenant: $order->server));
                        }

                        return;
                    }

                    // ----------------------------------------------------------
                    // First-time trial (paid product with trial_days > 0)
                    // ----------------------------------------------------------
                    if ($price->hasTrial()) {
                        $hasUsedTrial = Order::where('customer_id', $customer->id)
                            ->where('product_price_id', $price->id)
                            ->where('is_trial', true)
                            ->exists();

                        if (!$hasUsedTrial) {
                            /** @var Order $order */
                            $order = Order::create([
                                'customer_id'      => $customer->id,
                                'product_price_id' => $price->id,
                                'status'           => OrderStatus::Pending->value,
                            ]);

                            $order->activateTrial($price->trial_days);
                            $order->refresh();

                            Notification::make()
                                ->title('Trial started!')
                                ->body("Your {$price->trial_days}-day trial has begun.")
                                ->success()
                                ->send();

                            return;
                        }
                    }

                    // ----------------------------------------------------------
                    // Paid checkout — route to the active gateway
                    // ----------------------------------------------------------
                    $price->sync();

                    $gateway = config('billing.active_gateway', 'stripe');

                    /** @var Order $order */
                    $order = Order::create([
                        'customer_id'      => $customer->id,
                        'product_price_id' => $price->id,
                        'payment_gateway'  => $gateway,
                        'status'           => OrderStatus::Pending->value,
                    ]);

                    if ($gateway === PaymentGateway::PayPal->value) {
                        try {
                            $approvalUrl = app(PayPalService::class)->createOrder($order, $price);
                            return $this->redirect($approvalUrl);
                        } catch (RuntimeException $e) {
                            report($e);
                            $order->close();
                            Notification::make()
                                ->title('Payment unavailable')
                                ->body('Could not initiate PayPal checkout. Please try again.')
                                ->danger()
                                ->send();
                            return;
                        }
                    }

                    // Stripe
                    try {
                        return $this->redirect($order->getCheckoutSession()->url);
                    } catch (\Exception $e) {
                        report($e);
                        $order->close();
                        Notification::make()
                            ->title('Payment unavailable')
                            ->body('Could not initiate Stripe checkout. Please try again.')
                            ->danger()
                            ->send();
                    }
                });
        }

        return $schema
            ->record($this->product)
            ->components([
                Section::make()
                    ->heading($this->product->getLabel())
                    ->description($this->product->description)
                    ->columns(6)
                    ->schema([
                        TextEntry::make('cpu')
                            ->label('CPU')
                            ->icon('tabler-cpu')
                            ->formatStateUsing(fn ($state) => $state === 0 ? 'Unlimited' : $state . ' %')
                            ->columnSpan(2),
                        TextEntry::make('memory')
                            ->icon('tabler-database')
                            ->formatStateUsing(fn ($state) => $state === 0 ? 'Unlimited' : Number::format($state / (config('panel.use_binary_prefix') ? 1024 : 1000), 2, locale: auth()->user()->language) . (config('panel.use_binary_prefix') ? ' GiB' : ' GB'))
                            ->columnSpan(2),
                        TextEntry::make('disk')
                            ->icon('tabler-folder')
                            ->formatStateUsing(fn ($state) => $state === 0 ? 'Unlimited' : Number::format($state / (config('panel.use_binary_prefix') ? 1024 : 1000), 2, locale: auth()->user()->language) . (config('panel.use_binary_prefix') ? ' GiB' : ' GB'))
                            ->columnSpan(2),
                        TextEntry::make('backup_limit')
                            ->inlineLabel()
                            ->columnSpan(3)
                            ->visible(fn ($state) => $state > 0),
                        TextEntry::make('database_limit')
                            ->inlineLabel()
                            ->columnSpan(3)
                            ->visible(fn ($state) => $state > 0),
                        Actions::make($actions)
                            ->columnSpanFull()
                            ->fullWidth(),
                    ]),
            ]);
    }
}
