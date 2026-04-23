<?php

namespace Fywolf\Billing\Filament\App\Widgets;

use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Enums\PaymentGateway;
use Fywolf\Billing\Filament\App\Pages\OrderComplete;
use Fywolf\Billing\Models\Coupon;
use Fywolf\Billing\Models\Customer;
use Fywolf\Billing\Models\Order;
use Fywolf\Billing\Models\OrderExpansion;
use Fywolf\Billing\Models\Pack;
use Fywolf\Billing\Models\PackExpansion;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class ProductWidget extends Widget
{
    protected string $view = 'billing::widget';

    public ?Pack $product = null;

    public string $couponCode = '';

    public ?array $couponValidation = null;

    /** @var array<int> */
    public array $selectedExpansionIds = [];

    public function updatedCouponCode(): void
    {
        $this->couponValidation = null;
    }

    public function toggleExpansion(int $packExpansionId): void
    {
        if (in_array($packExpansionId, $this->selectedExpansionIds, true)) {
            $this->selectedExpansionIds = array_values(
                array_filter($this->selectedExpansionIds, fn ($id) => $id !== $packExpansionId)
            );
        } else {
            $this->selectedExpansionIds[] = $packExpansionId;
        }
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

    public function placeOrder(int $priceId): void
    {
        $price = $this->product->prices->find($priceId);

        if (!$price) {
            return;
        }

        if (!$this->product->isAvailable()) {
            $reason = !$this->product->is_enabled
                ? 'This pack is currently unavailable.'
                : 'This pack is currently out of stock.';

            Notification::make()
                ->title('Pack unavailable')
                ->body($reason)
                ->danger()
                ->send();
            return;
        }

        $selectedExpansions = $this->resolveSelectedExpansions();

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
                'customer_id'     => $customer->id,
                'pack_price_id'   => $price->id,
                'payment_gateway' => PaymentGateway::Trial->value,
                'status'          => OrderStatus::Pending->value,
            ]);
            $this->attachExpansions($order, $selectedExpansions);
            $order->activate(null);
            $order->refresh();
            $token = $order->generateConfirmationToken();
            $this->redirect(OrderComplete::getUrl(['token' => $token], panel: 'app'));
            return;
        }

        // Trial
        if ($price->hasTrial()) {
            $hasUsedTrial = Order::where('customer_id', $customer->id)
                ->where('pack_price_id', $price->id)
                ->where('is_trial', true)
                ->exists();

            if (!$hasUsedTrial) {
                /** @var Order $order */
                $order = Order::create([
                    'customer_id'   => $customer->id,
                    'pack_price_id' => $price->id,
                    'status'        => OrderStatus::Pending->value,
                ]);
                $this->attachExpansions($order, $selectedExpansions);
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
            'customer_id'     => $customer->id,
            'pack_price_id'   => $price->id,
            'coupon_id'       => $couponId,
            'payment_gateway' => PaymentGateway::Stripe->value,
            'status'          => OrderStatus::Pending->value,
        ]);

        $this->attachExpansions($order, $selectedExpansions);

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

    /** @return \Illuminate\Support\Collection<int, PackExpansion> */
    private function resolveSelectedExpansions(): \Illuminate\Support\Collection
    {
        if (empty($this->selectedExpansionIds)) {
            return collect();
        }

        return $this->product->packExpansions
            ->whereIn('id', $this->selectedExpansionIds)
            ->filter(fn (PackExpansion $pe) => $pe->is_enabled && $pe->expansion->isAvailable())
            ->values();
    }

    /** @param \Illuminate\Support\Collection<int, PackExpansion> $expansions */
    private function attachExpansions(Order $order, \Illuminate\Support\Collection $expansions): void
    {
        foreach ($expansions as $packExpansion) {
            OrderExpansion::create([
                'order_id'          => $order->id,
                'pack_expansion_id' => $packExpansion->id,
                'price_paid'        => $packExpansion->effectivePrice(),
            ]);
        }
    }
}
