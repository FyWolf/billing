<?php

namespace Fywolf\Billing\Filament\App\Widgets;

use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Enums\PaymentGateway;
use Fywolf\Billing\Filament\App\Pages\OrderComplete;
use Fywolf\Billing\Models\Coupon;
use Fywolf\Billing\Models\Customer;
use Fywolf\Billing\Models\Order;
use Fywolf\Billing\Models\Product;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class ProductWidget extends Widget
{
    protected string $view = 'billing::widget'; // @phpstan-ignore property.defaultValue

    public ?Product $product = null;

    public string $couponCode = '';

    /** null = not yet validated, array = result of last validation attempt */
    public ?array $couponValidation = null;

    public function updatedCouponCode(): void
    {
        $this->couponValidation = null;
    }

    public function validateCoupon(): void
    {
        if (empty($this->couponCode)) {
            $this->couponValidation = null;
            return;
        }

        $coupon = Coupon::findByCode($this->couponCode);

        if (!$coupon) {
            $this->couponValidation = [
                'valid'   => false,
                'message' => 'This code is invalid or has expired.',
            ];
            return;
        }

        if ($coupon->amount_off) {
            $formatter = new \NumberFormatter(user()->language, \NumberFormatter::CURRENCY);
            $discount  = $formatter->formatCurrency($coupon->amount_off, config('billing.currency'));
            $message   = "{$discount} off";
        } elseif ($coupon->percent_off) {
            $message = "{$coupon->percent_off}% off";
        } else {
            $message = 'Discount applied';
        }

        $this->couponValidation = [
            'valid'   => true,
            'message' => $message,
        ];
    }

    public function formatSize(int $mb): string
    {
        if ($mb === 0) {
            return 'Unlimited';
        }

        $binary = (bool) config('panel.use_binary_prefix');
        $value  = $mb / ($binary ? 1024 : 1000);
        $unit   = $binary ? 'GiB' : 'GB';

        return number_format($value, $mb % ($binary ? 1024 : 1000) === 0 ? 0 : 1) . ' ' . $unit;
    }

    /**
     * Called from the blade view when the user clicks an order button.
     */
    public function placeOrder(int $priceId): void
    {
        $price = $this->product->prices->find($priceId);

        if (!$price) {
            return;
        }

        if (!$this->product->is_enabled) {
            Notification::make()
                ->title('Product unavailable')
                ->body('This product is currently unavailable.')
                ->danger()
                ->send();
            return;
        }

        $available = $this->product->availableStock();
        if ($available !== null && $available <= 0) {
            Notification::make()
                ->title('Out of stock')
                ->body('This product is currently sold out.')
                ->danger()
                ->send();
            return;
        }

        /** @var Customer $customer */
        $customer = Customer::firstOrCreate(
            ['user_id' => user()->id],
            [
                'first_name' => user()->username,
                'last_name'  => user()->username,
            ]
        );

        // Free tier
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
            $this->redirect(OrderComplete::getUrl(['token' => $token], panel: 'app'));
            return;
        }

        // Trial
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
                $this->redirect(OrderComplete::getUrl(['token' => $token], panel: 'app'));
                return;
            }
        }

        // Stripe checkout
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
            $this->redirect($order->getCheckoutSession()->url);
        } catch (\Exception $e) {
            report($e);
            $order->close();
            Notification::make()
                ->title('Payment unavailable')
                ->body('Could not initiate Stripe checkout. Please try again.')
                ->danger()
                ->send();
        }
    }
}
