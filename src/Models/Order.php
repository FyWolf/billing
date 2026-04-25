<?php

namespace Fywolf\Billing\Models;

use App\Models\EggVariable;
use App\Models\Server;
use App\Models\ServerVariable;
use Fywolf\Billing\Contracts\PackProvisionerContract;
use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Enums\PaymentGateway;
use Fywolf\Billing\Enums\PriceInterval;
use Fywolf\Billing\Jobs\CreateServerJob;
use Fywolf\Billing\Mail\OrderConfirmationMail;
use Fywolf\Billing\ProvisionerRegistry;
use Exception;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

/**
 * @property int $id
 * @property ?string $stripe_checkout_id
 * @property ?string $stripe_payment_id
 * @property ?string $stripe_subscription_id
 * @property ?string $payment_gateway
 * @property bool $is_trial
 * @property OrderStatus $status
 * @property ?Carbon $expires_at
 * @property ?Carbon $grace_notified_at
 * @property ?Carbon $cancelled_at
 * @property ?string $confirmation_token
 * @property ?Carbon $confirmation_token_expires_at
 * @property int $customer_id
 * @property Customer $customer
 * @property int $pack_price_id
 * @property PackPrice $packPrice
 * @property ?int $pending_pack_price_id
 * @property ?PackPrice $pendingPackPrice
 * @property ?int $coupon_id
 * @property ?Coupon $coupon
 * @property ?int $server_id
 * @property ?Server $server
 * @property Collection|OrderExpansion[] $orderExpansions
 */
class Order extends Model implements HasLabel
{
    protected $table = 'billing_orders';

    protected $fillable = [
        'stripe_checkout_id',
        'stripe_payment_id',
        'stripe_subscription_id',
        'payment_gateway',
        'is_trial',
        'status',
        'expires_at',
        'grace_notified_at',
        'cancelled_at',
        'confirmation_token',
        'confirmation_token_expires_at',
        'customer_id',
        'pack_price_id',
        'pending_pack_price_id',
        'coupon_id',
        'server_id',
    ];

    protected function casts(): array
    {
        return [
            'status'                        => OrderStatus::class,
            'expires_at'                    => 'datetime',
            'grace_notified_at'             => 'datetime',
            'cancelled_at'                  => 'datetime',
            'confirmation_token_expires_at' => 'datetime',
            'is_trial'                      => 'bool',
        ];
    }

    // Relationships

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function packPrice(): BelongsTo
    {
        return $this->belongsTo(PackPrice::class, 'pack_price_id');
    }

    public function pendingPackPrice(): BelongsTo
    {
        return $this->belongsTo(PackPrice::class, 'pending_pack_price_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function orderExpansions(): HasMany
    {
        return $this->hasMany(OrderExpansion::class, 'order_id');
    }

    // Helpers

    public function getLabel(): string
    {
        return "Order #{$this->id}";
    }

    public function getProvisioner(): PackProvisionerContract
    {
        $slug = $this->packPrice->pack->provisioner ?? 'wings';

        return app(ProvisionerRegistry::class)->get($slug);
    }

    /**
     * Generate a unique confirmation token valid for 24 hours.
     * The raw token is returned to the caller; only a SHA-256 hash is persisted.
     */
    public function generateConfirmationToken(): string
    {
        $token = Str::random(64);

        $this->update([
            'confirmation_token'            => hash('sha256', $token),
            'confirmation_token_expires_at' => now('UTC')->addHours(24),
        ]);

        return $token;
    }

    /**
     * Find an order by its confirmation token, or null if expired/invalid.
     */
    public static function findByConfirmationToken(string $token): ?self
    {
        return static::where('confirmation_token', hash('sha256', $token))
            ->where('confirmation_token_expires_at', '>', now('UTC'))
            ->first();
    }

    public function getPaymentUrl(): string
    {
        return $this->getCheckoutSession()->url;
    }

    // Stripe

    private function getOrCreateStripeCustomerId(): string
    {
        $customer = $this->customer;

        if ($customer->stripe_customer_id) {
            return $customer->stripe_customer_id;
        }

        /** @var StripeClient $stripeClient */
        $stripeClient = app(StripeClient::class);

        $stripeCustomer = $stripeClient->customers->create([
            'email'    => $customer->user->email,
            'name'     => $customer->first_name . ' ' . $customer->last_name,
            'metadata' => ['customer_id' => $customer->id],
        ]);

        $customer->update(['stripe_customer_id' => $stripeCustomer->id]);

        return $stripeCustomer->id;
    }

    public function getCheckoutSession(): Session
    {
        /** @var StripeClient $stripeClient */
        $stripeClient = app(StripeClient::class);

        if (is_null($this->stripe_checkout_id)) {
            $isSubscription = $this->packPrice->renewable;

            $lineItems = [
                [
                    'price'    => $this->packPrice->stripe_id,
                    'quantity' => 1,
                ],
            ];

            $this->loadMissing('orderExpansions.packExpansion.expansion');

            foreach ($this->orderExpansions as $orderExpansion) {
                $pricePaid = (float) $orderExpansion->price_paid;

                if ($pricePaid <= 0) {
                    continue;
                }

                $expansion = $orderExpansion->packExpansion->expansion;
                $expansion->syncStripe();

                $priceData = [
                    'currency'    => config('billing.currency'),
                    'product'     => $expansion->stripe_id,
                    'unit_amount' => (int) round($pricePaid * 100),
                ];

                if ($isSubscription) {
                    $priceData['recurring'] = [
                        'interval'       => $this->packPrice->interval_type->value,
                        'interval_count' => $this->packPrice->interval_value,
                    ];
                }

                $lineItems[] = [
                    'price_data' => $priceData,
                    'quantity'   => 1,
                ];
            }

            $sessionData = [
                'customer'    => $this->getOrCreateStripeCustomerId(),
                'success_url' => route('billing.checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => route('billing.checkout.cancel') . '?session_id={CHECKOUT_SESSION_ID}',
                'line_items'  => $lineItems,
                'mode'        => $isSubscription ? 'subscription' : 'payment',
                'metadata'    => [
                    'order_id'    => $this->id,
                    'customer_id' => $this->customer_id,
                ],
            ];

            if ($isSubscription) {
                $sessionData['subscription_data'] = [
                    'metadata' => [
                        'order_id'    => $this->id,
                        'customer_id' => $this->customer_id,
                    ],
                ];
            }

            if ($this->coupon_id && $this->coupon?->stripe_coupon_id) {
                $sessionData['discounts'] = [
                    ['coupon' => $this->coupon->stripe_coupon_id],
                ];
            } else {
                $sessionData['allow_promotion_codes'] = true;
            }

            $session = $stripeClient->checkout->sessions->create($sessionData);

            $this->update(['stripe_checkout_id' => $session->id]);

            return $session;
        }

        return $stripeClient->checkout->sessions->retrieve($this->stripe_checkout_id);
    }

    private function expireStripeCheckoutSession(): void
    {
        if (!is_null($this->stripe_checkout_id)) {
            try {
                /** @var StripeClient $stripeClient */
                $stripeClient = app(StripeClient::class);
                $session = $stripeClient->checkout->sessions->retrieve($this->stripe_checkout_id);
                if ($session->status === Session::STATUS_OPEN) {
                    $stripeClient->checkout->sessions->expire($session->id);
                }
            } catch (Exception $e) {
                report($e);
            }
        }
    }

    /**
     * Cancel the Stripe Subscription at the end of the current billing period.
     */
    public function cancelSubscription(): void
    {
        if ($this->stripe_subscription_id) {
            try {
                /** @var StripeClient $stripeClient */
                $stripeClient = app(StripeClient::class);
                $stripeClient->subscriptions->update($this->stripe_subscription_id, [
                    'cancel_at_period_end' => true,
                ]);
            } catch (Exception $e) {
                report($e);
            }
        }

        $this->update([
            'status'       => OrderStatus::Cancelled,
            'cancelled_at' => now('UTC'),
        ]);

        AuditLog::record('order_cancelled', [
            'expires_at' => $this->expires_at?->toIso8601String(),
        ], $this);
    }

    /**
     * Refund the order via Stripe and close it.
     *
     * @param int|null $amountInCents Partial refund amount in cents, or null for a full refund.
     * @return string The Stripe Refund ID.
     */
    public function refund(?int $amountInCents = null): string
    {
        /** @var StripeClient $stripeClient */
        $stripeClient = app(StripeClient::class);

        $paymentIntentId = $this->resolvePaymentIntentId($stripeClient);

        $refundData = ['payment_intent' => $paymentIntentId];

        if ($amountInCents !== null) {
            $refundData['amount'] = $amountInCents;
        }

        $refund = $stripeClient->refunds->create($refundData);

        if ($this->stripe_subscription_id) {
            try {
                $stripeClient->subscriptions->cancel($this->stripe_subscription_id);
            } catch (Exception $e) {
                report($e);
            }
        }

        $this->close();

        AuditLog::record('order_refunded', [
            'stripe_refund_id' => $refund->id,
            'amount'           => $refund->amount,
            'currency'         => $refund->currency,
            'full_refund'      => $amountInCents === null,
        ], $this);

        return $refund->id;
    }

    private function resolvePaymentIntentId(StripeClient $stripeClient): string
    {
        if ($this->stripe_payment_id) {
            return $this->stripe_payment_id;
        }

        if ($this->stripe_subscription_id) {
            $invoices = $stripeClient->invoices->all([
                'subscription' => $this->stripe_subscription_id,
                'limit'        => 10,
            ]);

            foreach ($invoices->data as $invoice) {
                if ($invoice->payment_intent) {
                    return $invoice->payment_intent;
                }
            }
        }

        if ($this->stripe_checkout_id) {
            $session = $stripeClient->checkout->sessions->retrieve($this->stripe_checkout_id);

            if ($session->payment_intent) {
                return $session->payment_intent;
            }
        }

        throw new Exception("No payment intent found for Order #{$this->id}");
    }

    // Order lifecycle

    public function activate(?string $subscriptionId, ?int $currentPeriodEnd = null): void
    {
        $expireDate = $currentPeriodEnd
            ? Carbon::createFromTimestamp($currentPeriodEnd)
            : match ($this->packPrice->interval_type) {
                PriceInterval::Day   => now('UTC')->addDays($this->packPrice->interval_value),
                PriceInterval::Week  => now('UTC')->addWeeks($this->packPrice->interval_value),
                PriceInterval::Month => now('UTC')->addMonths($this->packPrice->interval_value),
                PriceInterval::Year  => now('UTC')->addYears($this->packPrice->interval_value),
            };

        $this->expireStripeCheckoutSession();

        $this->update([
            'stripe_subscription_id' => $subscriptionId ?? $this->stripe_subscription_id,
            'status'                 => OrderStatus::Active,
            'expires_at'             => $expireDate,
        ]);

        AuditLog::record('order_activated', [
            'payment_gateway'        => $this->payment_gateway,
            'stripe_subscription_id' => $subscriptionId,
            'expires_at'             => $expireDate->toIso8601String(),
        ], $this);

        $provisioner = $this->getProvisioner();

        if ($provisioner->isProvisioned($this)) {
            $provisioner->unsuspend($this);
        } else {
            CreateServerJob::dispatch($this->id);
        }

        $this->sendOrderConfirmationEmail();
    }

    public function renew(int $currentPeriodEnd): void
    {
        $wasGracePeriod = $this->status === OrderStatus::GracePeriod;

        $updates = [
            'status'     => OrderStatus::Active,
            'expires_at' => Carbon::createFromTimestamp($currentPeriodEnd),
        ];

        if ($this->pending_pack_price_id) {
            $newPrice = PackPrice::find($this->pending_pack_price_id);

            if ($newPrice) {
                $updates['pack_price_id']        = $newPrice->id;
                $updates['pending_pack_price_id'] = null;

                if ($this->stripe_subscription_id) {
                    try {
                        /** @var StripeClient $stripeClient */
                        $stripeClient = app(StripeClient::class);
                        $subscription = $stripeClient->subscriptions->retrieve($this->stripe_subscription_id);
                        $stripeClient->subscriptions->update($this->stripe_subscription_id, [
                            'items' => [[
                                'id'    => $subscription->items->data[0]->id,
                                'price' => $newPrice->stripe_id,
                            ]],
                            'proration_behavior' => 'none',
                        ]);
                    } catch (Exception $e) {
                        report($e);
                    }
                }

                AuditLog::record('order_plan_change_applied', [
                    'new_price_id'   => $newPrice->id,
                    'new_price_name' => $newPrice->name,
                    'applied_at'     => now('UTC')->toIso8601String(),
                ], $this);
            }
        }

        $this->update($updates);
        $this->load('packPrice');

        if ($wasGracePeriod) {
            $provisioner = $this->getProvisioner();

            if ($provisioner->isProvisioned($this)) {
                $provisioner->unsuspend($this);
            }
        }

        AuditLog::record('order_renewed', [
            'new_expires_at' => Carbon::createFromTimestamp($currentPeriodEnd)->toIso8601String(),
        ], $this);
    }

    public function activateTrial(int $trialDays): void
    {
        $this->update([
            'status'          => OrderStatus::Active,
            'is_trial'        => true,
            'payment_gateway' => PaymentGateway::Trial->value,
            'expires_at'      => now('UTC')->addDays($trialDays),
        ]);

        AuditLog::record('order_trial_activated', ['trial_days' => $trialDays], $this);

        CreateServerJob::dispatch($this->id);

        $this->sendOrderConfirmationEmail();
    }

    public function close(): void
    {
        $this->getProvisioner()->suspend($this);

        $this->expireStripeCheckoutSession();

        $this->update([
            'stripe_checkout_id' => null,
            'status'             => OrderStatus::Closed,
        ]);

        AuditLog::record('order_closed', [], $this);
    }

    public function enterGracePeriod(): void
    {
        $this->update(['status' => OrderStatus::GracePeriod]);

        AuditLog::record('order_grace_period_started', [
            'expires_at'         => $this->expires_at?->toIso8601String(),
            'grace_period_hours' => config('billing.grace_period_hours', 24),
        ], $this);
    }

    public function expire(): void
    {
        $this->getProvisioner()->suspend($this);

        if ($this->stripe_subscription_id) {
            try {
                /** @var StripeClient $stripeClient */
                $stripeClient = app(StripeClient::class);
                $stripeClient->subscriptions->cancel($this->stripe_subscription_id);
            } catch (Exception $e) {
                report($e);
            }
        }

        $this->expireStripeCheckoutSession();

        $this->update(['status' => OrderStatus::Expired]);

        AuditLog::record('order_expired', [
            'expired_at' => now('UTC')->toIso8601String(),
        ], $this);
    }

    private function sendOrderConfirmationEmail(): void
    {
        try {
            $this->load(['packPrice.pack', 'customer.user']);
            $email = $this->customer->user->email;

            Mail::to($email)->queue(new OrderConfirmationMail($this));
        } catch (Exception $e) {
            report($e);
        }
    }

    // Plan changes

    public function changePlan(PackPrice $newPrice): void
    {
        $oldPrice = $this->packPrice;

        if ($newPrice->pack_id !== $oldPrice->pack_id) {
            throw new \InvalidArgumentException(
                "Price #{$newPrice->id} does not belong to the same pack as the current price."
            );
        }

        $direction = $newPrice->cost > $oldPrice->cost ? 'upgrade' : 'downgrade';

        if ($this->stripe_subscription_id) {
            $this->update(['pending_pack_price_id' => $newPrice->id]);

            if ($this->server) {
                $this->applyEnvironmentOverrides($this->server, $newPrice);
            }

            AuditLog::record("order_plan_{$direction}_scheduled", [
                'old_price_id'   => $oldPrice->id,
                'old_price_name' => $oldPrice->name,
                'new_price_id'   => $newPrice->id,
                'new_price_name' => $newPrice->name,
            ], $this);
        } else {
            $this->update(['pack_price_id' => $newPrice->id]);
            $this->load('packPrice');

            if ($this->server) {
                $this->applyEnvironmentOverrides($this->server, $newPrice);
            }

            AuditLog::record("order_plan_{$direction}", [
                'old_price_id'   => $oldPrice->id,
                'old_price_name' => $oldPrice->name,
                'new_price_id'   => $newPrice->id,
                'new_price_name' => $newPrice->name,
            ], $this);
        }
    }

    private function applyEnvironmentOverrides(Server $server, PackPrice $price): void
    {
        $pack         = $price->pack;
        $eggVariables = EggVariable::where('egg_id', $pack->egg_id)->get();

        $overrides = [];
        if (!empty($price->environment_overrides)) {
            foreach ($price->environment_overrides as $override) {
                if (isset($override['variable'], $override['value'])) {
                    $overrides[$override['variable']] = $override['value'];
                }
            }
        }

        foreach ($eggVariables as $eggVariable) {
            $value = $overrides[$eggVariable->env_variable] ?? $eggVariable->default_value;

            ServerVariable::updateOrCreate(
                ['server_id' => $server->id, 'variable_id' => $eggVariable->id],
                ['variable_value' => $value]
            );
        }
    }
}
