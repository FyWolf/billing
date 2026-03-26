<?php

namespace Boy132\Billing\Models;

use App\Enums\SuspendAction;
use App\Models\Allocation;
use App\Models\EggVariable;
use App\Models\Objects\DeploymentObject;
use App\Models\Server;
use App\Models\ServerVariable;
use App\Services\Servers\ServerCreationService;
use App\Services\Servers\SuspensionService;
use Boy132\Billing\Enums\OrderStatus;
use Boy132\Billing\Enums\PaymentGateway;
use Boy132\Billing\Enums\PriceInterval;
use Boy132\Billing\Jobs\CreateServerJob;
use Exception;
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
 * @property ?string $payment_gateway
 * @property ?string $paypal_order_id
 * @property ?string $paypal_capture_id
 * @property bool $is_trial
 * @property OrderStatus $status
 * @property ?Carbon $expires_at
 * @property ?Carbon $grace_notified_at
 * @property int $customer_id
 * @property Customer $customer
 * @property int $product_price_id
 * @property ProductPrice $productPrice
 * @property ?int $server_id
 * @property ?Server $server
 */
class Order extends Model implements HasLabel
{
    protected $fillable = [
        'stripe_checkout_id',
        'stripe_payment_id',
        'payment_gateway',
        'paypal_order_id',
        'paypal_capture_id',
        'is_trial',
        'status',
        'expires_at',
        'grace_notified_at',
        'customer_id',
        'product_price_id',
        'server_id',
    ];

    protected function casts(): array
    {
        return [
            'status'            => OrderStatus::class,
            'expires_at'        => 'datetime',
            'grace_notified_at' => 'datetime',
            'is_trial'          => 'bool',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships (lowercase method calls — PHP is case-sensitive on statics)
    // -------------------------------------------------------------------------

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function productPrice(): BelongsTo
    {
        return $this->belongsTo(ProductPrice::class, 'product_price_id');
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
     * Check whether a customer already has an active or pending order for this price.
     * Call before creating a new order to prevent duplicates.
     */
    public static function hasDuplicateFor(int $customerId, int $productPriceId): bool
    {
        return static::where('customer_id', $customerId)
            ->where('product_price_id', $productPriceId)
            ->whereIn('status', [
                OrderStatus::Pending->value,
                OrderStatus::Active->value,
                OrderStatus::GracePeriod->value,
            ])
            ->exists();
    }

    /**
     * Returns the URL the user should visit to complete payment.
     * Gateway-agnostic: callers should not need to know which gateway is active.
     */
    public function getPaymentUrl(): string
    {
        if ($this->payment_gateway === PaymentGateway::PayPal->value) {
            return app(\Boy132\Billing\Services\PayPalService::class)->getApprovalUrl($this);
        }

        return $this->getCheckoutSession()->url;
    }

    // -------------------------------------------------------------------------
    // Stripe checkout
    // -------------------------------------------------------------------------

    public function getCheckoutSession(): Session
    {
        /** @var StripeClient $stripeClient */
        $stripeClient = app(StripeClient::class);

        if (is_null($this->stripe_checkout_id)) {
            $session = $stripeClient->checkout->sessions->create([
                'customer_email' => $this->customer->user->email,
                'success_url'    => route('billing.checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'     => route('billing.checkout.cancel') . '?session_id={CHECKOUT_SESSION_ID}',
                'line_items'     => [
                    [
                        'price'    => $this->productPrice->stripe_id,
                        'quantity' => 1,
                    ],
                ],
                'mode'                  => 'payment',
                'allow_promotion_codes' => true,
                'metadata'              => [
                    'order_id'    => $this->id,
                    'customer_id' => $this->customer_id,
                ],
                'branding_settings' => [
                    'display_name' => config('app.name'),
                ],
            ]);

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

    private function clearPaymentIds(): void
    {
        $this->expireStripeCheckoutSession();
        $this->update(['stripe_checkout_id' => null]);
    }

    // -------------------------------------------------------------------------
    // Order lifecycle
    // -------------------------------------------------------------------------

    /**
     * Activate the order after successful payment.
     *
     * @param string|null $paymentReference  Stripe payment_intent ID or PayPal capture ID
     */
    public function activate(?string $paymentReference): void
    {
        $expireDate = match ($this->productPrice->interval_type) {
            PriceInterval::Day   => now('UTC')->addDays($this->productPrice->interval_value),
            PriceInterval::Week  => now('UTC')->addWeeks($this->productPrice->interval_value),
            PriceInterval::Month => now('UTC')->addMonths($this->productPrice->interval_value),
            PriceInterval::Year  => now('UTC')->addYears($this->productPrice->interval_value),
        };

        $this->clearPaymentIds();

        $isPayPal = $this->payment_gateway === PaymentGateway::PayPal->value;

        $this->update([
            'stripe_payment_id'  => $isPayPal ? null : $paymentReference,
            'paypal_capture_id'  => $isPayPal ? $paymentReference : $this->paypal_capture_id,
            'status'             => OrderStatus::Active,
            'expires_at'         => $expireDate,
        ]);

        AuditLog::record('order_activated', [
            'payment_gateway'   => $this->payment_gateway,
            'payment_reference' => $paymentReference,
            'expires_at'        => $expireDate->toIso8601String(),
        ], $this);

        // Provision or unsuspend the server via a queued job so payment
        // callback returns immediately even if the node is slow.
        if ($this->server) {
            try {
                app(SuspensionService::class)->handle($this->server, SuspendAction::Unsuspend);
            } catch (Exception $exception) {
                report($exception);
            }
        } else {
            CreateServerJob::dispatch($this->id);
        }
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

        $this->clearPaymentIds();

        $this->update([
            'stripe_checkout_id' => null,
            'status'             => OrderStatus::Closed,
        ]);

        AuditLog::record('order_closed', [], $this);
    }

    /**
     * Move to grace period (called by CheckOrdersCommand when expires_at is reached).
     * Server stays online during the grace period.
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

        $this->clearPaymentIds();

        $this->update(['status' => OrderStatus::Expired]);

        AuditLog::record('order_expired', [
            'expired_at' => now('UTC')->toIso8601String(),
        ], $this);
    }

    /**
     * Legacy method kept for backwards compatibility.
     * Now delegates to grace-period-aware flow.
     */
    public function checkExpire(): bool
    {
        if (is_null($this->expires_at)) {
            return false;
        }

        $graceHours = (int) config('billing.grace_period_hours', 24);

        // Active → GracePeriod when expires_at is reached
        if ($this->status === OrderStatus::Active && now('UTC') >= $this->expires_at) {
            if ($graceHours > 0) {
                $this->enterGracePeriod();
            } else {
                $this->expire();
            }
            return true;
        }

        // GracePeriod → Expired when grace window is exhausted
        if ($this->status === OrderStatus::GracePeriod) {
            $graceDeadline = $this->expires_at->clone()->addHours($graceHours);
            if (now('UTC') >= $graceDeadline) {
                $this->expire();
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Plan changes (upgrade / downgrade)
    // -------------------------------------------------------------------------

    /**
     * Switch this order to a different price tier and update the server's
     * startup variables to match the new price's environment overrides.
     */
    public function changePlan(ProductPrice $newPrice): void
    {
        $oldPrice = $this->productPrice;

        $this->update(['product_price_id' => $newPrice->id]);
        $this->load('productPrice');

        // Update the server's startup variables if it exists
        if ($this->server) {
            $this->applyEnvironmentOverrides($this->server, $newPrice);
        }

        $direction = $newPrice->cost > $oldPrice->cost ? 'upgrade' : 'downgrade';

        AuditLog::record("order_plan_{$direction}", [
            'old_price_id'   => $oldPrice->id,
            'old_price_name' => $oldPrice->name,
            'new_price_id'   => $newPrice->id,
            'new_price_name' => $newPrice->name,
        ], $this);
    }

    /**
     * Apply a price tier's environment overrides to an existing server.
     * Resets all overridable variables to egg defaults first, then applies
     * the new price's overrides so that downgrades properly remove old values.
     */
    private function applyEnvironmentOverrides(Server $server, ProductPrice $price): void
    {
        $product = $price->product;
        $eggVariables = EggVariable::where('egg_id', $product->egg_id)->get();

        // Build a map of env_variable => override value from the new price
        $overrides = [];
        if (!empty($price->environment_overrides)) {
            foreach ($price->environment_overrides as $override) {
                if (isset($override['variable'], $override['value'])) {
                    $overrides[$override['variable']] = $override['value'];
                }
            }
        }

        // Update each egg variable: use the override if set, otherwise reset to default
        foreach ($eggVariables as $eggVariable) {
            $value = $overrides[$eggVariable->env_variable] ?? $eggVariable->default_value;

            ServerVariable::updateOrCreate(
                ['server_id' => $server->id, 'variable_id' => $eggVariable->id],
                ['variable_value' => $value]
            );
        }
    }

    // -------------------------------------------------------------------------
    // Server provisioning (called directly by admin actions too)
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

        // Apply per-price variable overrides (e.g. locked player count)
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
            // Specific nodes configured — find a matching allocation manually
            $allocationQuery = Allocation::query()
                ->whereIn('node_id', $product->node_ids)
                ->whereNull('server_id');

            // Filter by required ports if specified
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
            // No specific nodes — use auto-deployment via tags
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
