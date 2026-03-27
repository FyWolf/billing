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
 * @property array|null $environment_overrides
 * @property int $product_id
 * @property Product $product
 */
class ProductPrice extends Model implements HasLabel
{
    protected $fillable = [
        'stripe_id',
        'product_id',
        'name',
        'cost',
        'renewable',
        'trial_days',
        'interval_type',
        'interval_value',
        'environment_overrides',
    ];

    protected function casts(): array
    {
        return [
            'renewable'              => 'bool',
            'interval_type'          => PriceInterval::class,
            'environment_overrides'  => 'array',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function getLabel(): string
    {
        return $this->interval_value . ' ' . str_plural($this->interval_type->getLabel(), $this->interval_value) . ' - ' . $this->formatCost();
    }

    public function sync(): void
    {
        if (!$this->isStripeEnabled()) {
            return;
        }

        $this->product->sync();

        /** @var StripeClient $stripeClient */
        $stripeClient = app(StripeClient::class);

        try {
            if (is_null($this->stripe_id)) {
                $priceData = [
                    'currency'    => config('billing.currency'),
                    'nickname'    => $this->name,
                    'product'     => $this->product->stripe_id,
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
            } else {
                $stripePrice = $stripeClient->prices->retrieve($this->stripe_id);

                // Stripe prices are immutable — recreate if amount, product, recurring config, or renewable flag changed
                $stripeIsRecurring = isset($stripePrice->recurring);
                $needsRecreate = $stripePrice->product !== $this->product->stripe_id
                    || $stripePrice->unit_amount !== (int) round($this->cost * 100)
                    || $stripeIsRecurring !== (bool) $this->renewable
                    || ($this->renewable && (
                        $stripePrice->recurring->interval !== $this->interval_type->value
                        || $stripePrice->recurring->interval_count !== $this->interval_value
                    ));

                if ($needsRecreate) {
                    $this->updateQuietly(['stripe_id' => null]);
                    $this->sync();
                }
            }
        } catch (\Exception $e) {
            report($e);
        }
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

    private static function isStripeEnabled(): bool
    {
        return !empty(config('billing.stripe.secret'));
    }
}
