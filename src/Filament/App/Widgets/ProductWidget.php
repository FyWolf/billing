<?php

namespace Fywolf\Billing\Filament\App\Widgets;

use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Enums\PaymentGateway;
use Fywolf\Billing\Filament\App\Pages\OrderComplete;
use Fywolf\Billing\Models\Coupon;
use Fywolf\Billing\Models\Customer;
use Fywolf\Billing\Models\Order;
use Fywolf\Billing\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

class ProductWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'billing::widget'; // @phpstan-ignore property.defaultValue

    public ?Product $product = null;

    public string $couponCode = '';

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
                    /** @var Customer $customer */
                    $customer = Customer::firstOrCreate(
                        ['user_id' => user()->id],
                        [
                            'first_name' => user()->username,
                            'last_name'  => user()->username,
                        ]
                    );

                    // ----------------------------------------------------------
                    // Free tier — activate immediately (no Stripe subscription)
                    // ----------------------------------------------------------
                    if ($price->isFree()) {
                        /** @var Order $order */
                        $order = Order::create([
                            'customer_id'      => $customer->id,
                            'product_price_id' => $price->id,
                            'payment_gateway'  => PaymentGateway::Trial->value,
                            'status'           => OrderStatus::Pending->value,
                        ]);

                        $order->activate(null);
                        $order->refresh();

                        $token = $order->generateConfirmationToken();
                        return redirect(OrderComplete::getUrl(['token' => $token], panel: 'app'));
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

                            $token = $order->generateConfirmationToken();
                            return redirect(OrderComplete::getUrl(['token' => $token], panel: 'app'));
                        }
                    }

                    // ----------------------------------------------------------
                    // Paid — create Stripe subscription checkout
                    // ----------------------------------------------------------
                    $price->sync();

                    $couponId = null;
                    if ($this->couponCode) {
                        $coupon = Coupon::findByCode($this->couponCode);
                        if (!$coupon) {
                            Notification::make()
                                ->title('Invalid coupon')
                                ->body('The coupon code you entered is not valid or has expired.')
                                ->danger()
                                ->send();
                            return;
                        }
                        $couponId = $coupon->id;
                    }

                    /** @var Order $order */
                    $order = Order::create([
                        'customer_id'      => $customer->id,
                        'product_price_id' => $price->id,
                        'coupon_id'        => $couponId,
                        'payment_gateway'  => PaymentGateway::Stripe->value,
                        'status'           => OrderStatus::Pending->value,
                    ]);

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
                        Placeholder::make('coupon_code_input')
                            ->label('Coupon Code')
                            ->content(new HtmlString(
                                '<input type="text" wire:model.defer="couponCode"'
                                . ' placeholder="Enter coupon code (optional)"'
                                . ' style="width:100%;padding:0.5rem 0.75rem;border-radius:0.5rem;border:1px solid;font-size:0.875rem;"'
                                . ' class="border-gray-300 bg-white text-gray-950 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />'
                            ))
                            ->columnSpanFull(),
                        Actions::make($actions)
                            ->columnSpanFull()
                            ->fullWidth(),
                    ]),
            ]);
    }
}
