<?php

namespace Fywolf\Billing\Models;

use App\Enums\SuspendAction;
use App\Models\Allocation;
use App\Models\EggVariable;
use App\Models\Objects\DeploymentObject;
use App\Models\Server;
use App\Models\ServerVariable;
use App\Services\Servers\ServerCreationService;
use App\Services\Servers\SuspensionService;
use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Enums\PaymentGateway;
use Fywolf\Billing\Enums\PriceInterval;
use Fywolf\Billing\Jobs\CreateServerJob;
use Fywolf\Billing\Mail\OrderConfirmationMail;
use Exception;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property int $product_price_id
 * @property ProductPrice $productPrice
 * @property ?int $pending_price_id
 * @property ?ProductPrice $pendingPrice
 * @property ?int $coupon_id
 * @property ?Coupon $coupon
 * @property ?int $server_id
 * @property ?Server $server
 */
class Order extends Model implements HasLabel
{
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
        'product_price_id',
        'pending_price_id',
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

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function productPrice(): BelongsTo
    {
        return $this->belongsTo(ProductPrice::class, 'product_price_id');
    }

    public function pendingPrice(): BelongsTo
    {
        return $this->belongsTo(ProductPrice::class, 'pending_price_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function getLabel(): string
    {
        return "Order #{$this->id}";
    }

    /**
     * Generate a unique confirmation token valid for 24 hours.
     */
    public function generateConfirmationToken(): string
    {
        $token = Str::random(64);

        $this->update([
            'confirmation_token'            => $token,
            'confirmation_token_expires_at' => now('UTC')->addHours(24),
        ]);

        return $token;
    }

    /**
     * Find an order by its confirmation token, or null if expired/invalid.
     */
    public static function findByConfirmationToken(string $token): ?self
    {
        return static::where('confirmation_token', $token)
            ->where('confirmation_token_expires_at', '>', now('UTC'))
            ->first();
    }

    /**
     * Returns the Stripe Checkout URL for this order.
     */
    public function getPaymentUrl(): string
    {
        return $this->getCheckoutSession()->url;
    }

    // -------------------------------------------------------------------------
    // Stripe Customer
    // -------------------------------------------------------------------------

    /**
     * Ensure a Stripe Customer exists for this order's customer and return the ID.
     */
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

    // -------------------------------------------------------------------------
    // Stripe Checkout
    // -------------------------------------------------------------------------

    public function getCheckoutSession(): Session
    {
        /** @var StripeClient $stripeClient */
        $stripeClient = app(StripeClient::class);

        if (is_null($this->stripe_checkout_id)) {
            $isSubscription = $this->productPrice->renewable;

            $sessionData = [
                'customer'    => $this->getOrCreateStripeCustomerId(),
                'success_url' => route('billing.checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => route('billing.checkout.cancel') . '?session_id={CHECKOUT_SESSION_ID}',
                'line_items'  => [
                    [
                        'price'    => $this->productPrice->stripe_id,
                        'quantity' => 1,
                    ],
                ],
                'mode'     => $isSubscription ? 'subscription' : 'payment',
                'metadata' => [
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

    // -------------------------------------------------------------------------
    // Stripe Subscription management
    // -------------------------------------------------------------------------

    /**
     * Cancel the Stripe Subscription at the end of the current billing period.
     * The server remains active until expires_at is reached.
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
     * @param int|null $amountInCents  Partial refund amount in cents, or null for a full refund.
     * @return string The Stripe Refund ID.
     */
    public function refund(?int $amountInCents = null): string
    {
        /** @var StripeClient $stripeClient */
        $stripeClient = app(StripeClient::class);

        // Resolve the payment intent to refund
        $paymentIntentId = $this->resolvePaymentIntentId($stripeClient);

        $refundData = ['payment_intent' => $paymentIntentId];

        if ($amountInCents !== null) {
            $refundData['amount'] = $amountInCents;
        }

        $refund = $stripeClient->refunds->create($refundData);

        // Immediately cancel subscription and close the order
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

    /**
     * Find the Stripe PaymentIntent ID for this order.
     */
    private function resolvePaymentIntentId(StripeClient $stripeClient): string
    {
        // 1. Stored payment ID
        if ($this->stripe_payment_id) {
            return $this->stripe_payment_id;
        }

        // 2. Subscription → latest invoice → payment intent
        if ($this->stripe_subscription_id) {
            $subscription = $stripeClient->subscriptions->retrieve(
                $this->stripe_subscription_id,
                ['expand' => ['latest_invoice']],
            );

            if ($subscription->latest_invoice?->payment_intent) {
                return $subscription->latest_invoice->payment_intent;
            }
        }

        // 3. Checkout session → payment intent
        if ($this->stripe_checkout_id) {
            $session = $stripeClient->checkout->sessions->retrieve($this->stripe_checkout_id);

            if ($session->payment_intent) {
                return $session->payment_intent;
            }
        }

        throw new Exception("No payment intent found for Order #{$this->id}");
    }

    // -------------------------------------------------------------------------
    // Order lifecycle
    // -------------------------------------------------------------------------

    /**
     * Activate the order after successful payment.
     *
     * @param string|null $subscriptionId     Stripe Subscription ID
     * @param int|null    $currentPeriodEnd   Unix timestamp from Stripe subscription
     */
    public function activate(?string $subscriptionId, ?int $currentPeriodEnd = null): void
    {
        $expireDate = $currentPeriodEnd
            ? Carbon::createFromTimestamp($currentPeriodEnd)
            : match ($this->productPrice->interval_type) {
                PriceInterval::Day   => now('UTC')->addDays($this->productPrice->interval_value),
                PriceInterval::Week  => now('UTC')->addWeeks($this->productPrice->interval_value),
                PriceInterval::Month => now('UTC')->addMonths($this->productPrice->interval_value),
                PriceInterval::Year  => now('UTC')->addYears($this->productPrice->interval_value),
            };

        $this->expireStripeCheckoutSession();

        $this->update([
            'stripe_subscription_id' => $subscriptionId ?? $this->stripe_subscription_id,
            'status'                 => OrderStatus::Active,
            'expires_at'             => $expireDate,
        ]);

        AuditLog::record('order_activated', [
            'payment_gateway'       => $this->payment_gateway,
            'stripe_subscription_id' => $subscriptionId,
            'expires_at'            => $expireDate->toIso8601String(),
        ], $this);

        if ($this->server) {
            try {
                app(SuspensionService::class)->handle($this->server, SuspendAction::Unsuspend);
            } catch (Exception $exception) {
                report($exception);
            }
        } else {
            CreateServerJob::dispatch($this->id);
        }

        $this->sendOrderConfirmationEmail();
    }

    /**
     * Renew the order after a successful recurring invoice payment.
     */
    public function renew(int $currentPeriodEnd): void
    {
        $wasGracePeriod = $this->status === OrderStatus::GracePeriod;

        $updates = [
            'status'     => OrderStatus::Active,
            'expires_at' => Carbon::createFromTimestamp($currentPeriodEnd),
        ];

        // Apply any scheduled plan change at this renewal boundary.
        if ($this->pending_price_id) {
            $newPrice = ProductPrice::find($this->pending_price_id);

            if ($newPrice) {
                $updates['product_price_id'] = $newPrice->id;
                $updates['pending_price_id'] = null;

                // Switch the Stripe subscription to the new price so that
                // the next billing cycle charges the correct amount.
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
        $this->load('productPrice');

        if ($wasGracePeriod && $this->server) {
            try {
                app(SuspensionService::class)->handle($this->server, SuspendAction::Unsuspend);
            } catch (Exception $exception) {
                report($exception);
            }
        }

        AuditLog::record('order_renewed', [
            'new_expires_at' => Carbon::createFromTimestamp($currentPeriodEnd)->toIso8601String(),
        ], $this);
    }

    /**
     * Activate as a free trial (no payment required).
     */
    public function activateTrial(int $trialDays): void
    {
        $this->update([
            'status'           => OrderStatus::Active,
            'is_trial'         => true,
            'payment_gateway'  => PaymentGateway::Trial->value,
            'expires_at'       => now('UTC')->addDays($trialDays),
        ]);

        AuditLog::record('order_trial_activated', ['trial_days' => $trialDays], $this);

        CreateServerJob::dispatch($this->id);

        $this->sendOrderConfirmationEmail();
    }

    /**
     * Close the order (user-initiated cancel or admin action).
     */
    public function close(): void
    {
        try {
            if ($this->server) {
                app(SuspensionService::class)->handle($this->server, SuspendAction::Suspend);
            }
        } catch (Exception $exception) {
            report($exception);
        }

        $this->expireStripeCheckoutSession();

        $this->update([
            'stripe_checkout_id' => null,
            'status'             => OrderStatus::Closed,
        ]);

        AuditLog::record('order_closed', [], $this);
    }

    /**
     * Move to grace period (called when expires_at is reached or invoice payment fails).
     */
    public function enterGracePeriod(): void
    {
        $this->update(['status' => OrderStatus::GracePeriod]);

        AuditLog::record('order_grace_period_started', [
            'expires_at'         => $this->expires_at?->toIso8601String(),
            'grace_period_hours' => config('billing.grace_period_hours', 24),
        ], $this);
    }

    /**
     * Expire the order after the grace period is exhausted.
     * Also cancels the Stripe Subscription as a safety measure.
     */
    public function expire(): void
    {
        try {
            if ($this->server) {
                app(SuspensionService::class)->handle($this->server, SuspendAction::Suspend);
            }
        } catch (Exception $exception) {
            report($exception);
        }

        // Cancel the Stripe Subscription to stop further billing
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

    // -------------------------------------------------------------------------
    // Email notifications
    // -------------------------------------------------------------------------

    private function sendOrderConfirmationEmail(): void
    {
        try {
            $this->load(['productPrice.product', 'customer.user']);
            $email = $this->customer->user->email;

            Mail::to($email)->queue(new OrderConfirmationMail($this));
        } catch (Exception $e) {
            report($e);
        }
    }

    // -------------------------------------------------------------------------
    // Plan changes (upgrade / downgrade)
    // -------------------------------------------------------------------------

    public function changePlan(ProductPrice $newPrice): void
    {
        $oldPrice = $this->productPrice;
        $direction = $newPrice->cost > $oldPrice->cost ? 'upgrade' : 'downgrade';

        if ($this->stripe_subscription_id) {
            // Subscription order: schedule the price change for next renewal.
            // The server's environment overrides are updated immediately so the
            // server spec is correct, but billing only switches next cycle.
            $this->update(['pending_price_id' => $newPrice->id]);

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
            // One-time order: no billing implication, apply immediately.
            $this->update(['product_price_id' => $newPrice->id]);
            $this->load('productPrice');

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

    private function applyEnvironmentOverrides(Server $server, ProductPrice $price): void
    {
        $product = $price->product;
        $eggVariables = EggVariable::where('egg_id', $product->egg_id)->get();

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

    // -------------------------------------------------------------------------
    // Server provisioning
    // -------------------------------------------------------------------------

    public function createServer(): Server
    {
        if ($this->server) {
            return $this->server;
        }

        $product = $this->productPrice->product;

        $environment = [];
        foreach ($product->egg->variables as $variable) {
            $environment[$variable->env_variable] = $variable->default_value;
        }

        if (!empty($this->productPrice->environment_overrides)) {
            foreach ($this->productPrice->environment_overrides as $override) {
                if (isset($override['variable'], $override['value'])) {
                    $environment[$override['variable']] = $override['value'];
                }
            }
        }

        $data = [
            'name'             => $this->getLabel() . ' (' . $product->getLabel() . ')',
            'owner_id'         => $this->customer->user->id,
            'egg_id'           => $product->egg->id,
            'cpu'              => $product->cpu,
            'memory'           => $product->memory,
            'disk'             => $product->disk,
            'swap'             => $product->swap,
            'io'               => $product->io_weight,
            'environment'      => $environment,
            'skip_scripts'     => false,
            'start_on_completion' => true,
            'oom_killer'       => false,
            'database_limit'   => $product->database_limit,
            'allocation_limit' => $product->allocation_limit,
            'backup_limit'     => $product->backup_limit,
        ];

        if (!empty($product->node_ids)) {
            $allocationQuery = Allocation::query()
                ->whereIn('node_id', $product->node_ids)
                ->whereNull('server_id');

            if (!empty($product->ports)) {
                $ports = [];
                foreach ($product->ports as $portRange) {
                    if (str_contains((string) $portRange, '-')) {
                        [$start, $end] = explode('-', $portRange, 2);
                        $ports = array_merge($ports, range((int) $start, (int) $end));
                    } else {
                        $ports[] = (int) $portRange;
                    }
                }
                $allocationQuery->whereIn('port', $ports);
            }

            $allocation = $allocationQuery->inRandomOrder()->first();

            if (!$allocation) {
                throw new \RuntimeException(
                    'No available allocations on the configured nodes'
                    . (!empty($product->ports) ? ' matching ports: ' . implode(', ', $product->ports) : '')
                    . '.'
                );
            }

            $data['node_id'] = $allocation->node_id;
            $data['allocation_id'] = $allocation->id;

            $server = app(ServerCreationService::class)->handle($data);
        } else {
            $object = new DeploymentObject();
            $object->setDedicated(false);
            $object->setTags($product->tags);
            $object->setPorts($product->ports);

            $server = app(ServerCreationService::class)->handle($data, $object);
        }

        $this->update(['server_id' => $server->id]);

        AuditLog::record('server_created', [
            'server_id'   => $server->id,
            'server_name' => $server->name,
        ], $this);

        return $server;
    }
}
