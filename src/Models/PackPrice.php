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

    private static function isStripeEnabled(): bool
    {
        return !empty(config('billing.stripe.secret'));
    }
}
