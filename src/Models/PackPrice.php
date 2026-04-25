<?php

namespace Fywolf\Billing\Models;

use Fywolf\Billing\Enums\PriceInterval;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NumberFormatter;
use Stripe\StripeClient;

/**
 * @property int $id
 * @property ?string $stripe_id
 * @property string $name
 * @property float $cost
 * @property bool $renewable
 * @property int $trial_days
 * @property PriceInterval $interval_type
 * @property int $interval_value
 * @property int $cores
 * @property int $memory
 * @property int $disk
 * @property int $swap
 * @property int $io_weight
 * @property int $allocation_limit
 * @property int $database_limit
 * @property int $backup_limit
 * @property array|null $environment_overrides
 * @property int $pack_id
 * @property Pack $pack
 */
class PackPrice extends Model implements HasLabel
{
    protected $table = 'billing_pack_prices';

    protected $fillable = [
        'stripe_id',
        'pack_id',
        'name',
        'cost',
        'renewable',
        'trial_days',
        'interval_type',
        'interval_value',
        'cores',
        'memory',
        'disk',
        'swap',
        'io_weight',
        'allocation_limit',
        'database_limit',
        'backup_limit',
        'environment_overrides',
    ];

    protected function casts(): array
    {
        return [
            'renewable'             => 'bool',
            'interval_type'         => PriceInterval::class,
            'environment_overrides' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::created(function (self $model) {
            $model->sync();
        });

        static::updated(function (self $model) {
            $model->sync();
        });
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::class, 'pack_id');
    }

    public function getLabel(): string
    {
        $billing = $this->interval_value . ' ' . str_plural($this->interval_type->getLabel(), $this->interval_value) . ' - ' . $this->formatCost();
        return $this->name ? $this->name . ' — ' . $billing : $billing;
    }

    public function sync(): void
    {
        if (!$this->isStripeEnabled()) {
            return;
        }

        $this->pack->sync();

        /** @var StripeClient $stripeClient */
        $stripeClient = app(StripeClient::class);

        try {
            if (is_null($this->stripe_id)) {
                $this->createStripePrice($stripeClient);
            } else {
                $stripePrice = $stripeClient->prices->retrieve($this->stripe_id);

                $stripeIsRecurring = isset($stripePrice->recurring);
                $needsRecreate = $stripePrice->product !== $this->pack->stripe_id
                    || $stripePrice->unit_amount !== (int) round($this->cost * 100)
                    || $stripeIsRecurring !== (bool) $this->renewable
                    || ($this->renewable && (
                        $stripePrice->recurring->interval !== $this->interval_type->value
                        || $stripePrice->recurring->interval_count !== $this->interval_value
                    ));

                if ($needsRecreate) {
                    $this->updateQuietly(['stripe_id' => null]);
                    $this->createStripePrice($stripeClient);
                }
            }
        } catch (\Exception $e) {
            report($e);
        }
    }

    private function createStripePrice(StripeClient $stripeClient): void
    {
        $priceData = [
            'currency'    => config('billing.currency'),
            'nickname'    => $this->name,
            'product'     => $this->pack->stripe_id,
            'unit_amount' => (int) round($this->cost * 100),
        ];

        if ($this->renewable) {
            $priceData['recurring'] = [
                'interval'       => $this->interval_type->value,
                'interval_count' => $this->interval_value,
            ];
        }

        $stripePrice = $stripeClient->prices->create($priceData);
        $this->updateQuietly(['stripe_id' => $stripePrice->id]);
    }

    public function isFree(): bool
    {
        return !$this->cost;
    }

    public function hasTrial(): bool
    {
        return $this->trial_days > 0;
    }

    public function formatCost(): string
    {
        if ($this->isFree()) {
            return 'Free';
        }

        $locale = auth()->user()?->language ?? 'en';
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($this->cost, config('billing.currency'));
    }

    public function formatCostRaw(float $amount): string
    {
        $locale = auth()->user()?->language ?? 'en';
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($amount, config('billing.currency'));
    }

    /**
     * Ensure this price has a Stripe ID, creating the product and price in Stripe if missing.
     * Unlike sync(), this throws on failure so callers get a real error.
     */
    public function ensureStripePrice(): void
    {
        if ($this->stripe_id) {
            return;
        }

        if (!$this->isStripeEnabled()) {
            throw new \RuntimeException('Stripe is not configured. Set STRIPE_SECRET in plugin settings.');
        }

        /** @var StripeClient $stripeClient */
        $stripeClient = app(StripeClient::class);

        if (!$this->pack->stripe_id) {
            $product = $stripeClient->products->create([
                'name'        => $this->pack->name,
                'description' => $this->pack->description,
            ]);
            $this->pack->updateQuietly(['stripe_id' => $product->id]);
            $this->pack->stripe_id = $product->id;
        }

        $priceData = [
            'currency'    => config('billing.currency'),
            'nickname'    => $this->name,
            'product'     => $this->pack->stripe_id,
            'unit_amount' => (int) round($this->cost * 100),
        ];

        if ($this->renewable) {
            $priceData['recurring'] = [
                'interval'       => $this->interval_type->value,
                'interval_count' => $this->interval_value,
            ];
        }

        $stripePrice = $stripeClient->prices->create($priceData);
        $this->updateQuietly(['stripe_id' => $stripePrice->id]);
        $this->stripe_id = $stripePrice->id;
    }

    private static function isStripeEnabled(): bool
    {
        return !empty(config('billing.stripe.secret'));
    }
}
