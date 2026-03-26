<?php

namespace Boy132\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * @property int $id
 * @property ?string $stripe_coupon_id
 * @property ?string $stripe_promotion_id
 * @property string $name
 * @property string $code
 * @property ?int $amount_off
 * @property ?int $percent_off
 * @property ?int $max_redemptions
 * @property ?Carbon $redeem_by
 */
class Coupon extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'stripe_coupon_id',
        'stripe_promotion_id',
        'name',
        'code',
        'amount_off',
        'percent_off',
        'max_redemptions',
        'redeem_by',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->code ??= Str::upper(Str::random(8));
        });

        static::created(function (self $model) {
            $model->sync();
        });

        static::updating(function (self $model) {
            $model->code ??= Str::upper(Str::random(8));
        });

        static::updated(function (self $model) {
            $model->sync();
        });

        static::deleted(function (self $model) {
            if (!self::isStripeEnabled()) {
                return;
            }
            $model->deactivateStripePromotion();
        });
    }

    public function sync(): void
    {
        if (!self::isStripeEnabled()) {
            return;
        }

        /** @var StripeClient $stripeClient */
        $stripeClient = app(StripeClient::class);

        try {
            if (is_null($this->stripe_coupon_id)) {
                $this->createStripeObjects($stripeClient);
            } else {
                $stripeCoupon = $stripeClient->coupons->retrieve($this->stripe_coupon_id);

                $changed = $stripeCoupon->amount_off !== $this->amount_off
                    || $stripeCoupon->percent_off !== $this->percent_off
                    || $stripeCoupon->max_redemptions !== $this->max_redemptions
                    || $stripeCoupon->redeem_by !== ($this->redeem_by ? $this->redeem_by->timestamp : null);

                if ($changed) {
                    // Stripe coupons are immutable — deactivate old and recreate
                    $this->deactivateStripePromotion();
                    $this->updateQuietly([
                        'stripe_coupon_id'    => null,
                        'stripe_promotion_id' => null,
                    ]);
                    $this->refresh();
                    $this->createStripeObjects($stripeClient);
                }
            }
        } catch (ApiErrorException $e) {
            report($e);
        }
    }

    private function createStripeObjects(StripeClient $stripeClient): void
    {
        $data = ['name' => $this->name];

        if ($this->amount_off) {
            $data['currency']   = config('billing.currency');
            $data['amount_off'] = (int) round($this->amount_off * 100);
        }

        if ($this->percent_off) {
            $data['percent_off'] = $this->percent_off;
        }

        if ($this->max_redemptions) {
            $data['max_redemptions'] = $this->max_redemptions;
        }

        if ($this->redeem_by) {
            $data['redeem_by'] = $this->redeem_by->timestamp;
        }

        $stripeCoupon = $stripeClient->coupons->create($data);

        // BUG FIX: promotionCodes->create takes 'coupon' as a direct key, not nested
        $stripePromoCode = $stripeClient->promotionCodes->create([
            'coupon' => $stripeCoupon->id,
            'code'   => $this->code,
        ]);

        $this->updateQuietly([
            'stripe_coupon_id'    => $stripeCoupon->id,
            'stripe_promotion_id' => $stripePromoCode->id,
        ]);
    }

    /**
     * Deactivate the Stripe promotion code, then delete the coupon.
     * Stripe does not allow deleting promotion codes, only deactivating them.
     */
    private function deactivateStripePromotion(): void
    {
        if (!self::isStripeEnabled()) {
            return;
        }

        /** @var StripeClient $stripeClient */
        $stripeClient = app(StripeClient::class);

        // Deactivate promotion code (cannot be deleted via API)
        if (!is_null($this->stripe_promotion_id)) {
            try {
                $stripeClient->promotionCodes->update(
                    $this->stripe_promotion_id,
                    ['active' => false]
                );
            } catch (ApiErrorException $e) {
                report($e);
            }
        }

        // Delete the underlying coupon (may fail if it has been redeemed)
        if (!is_null($this->stripe_coupon_id)) {
            try {
                $stripeClient->coupons->delete($this->stripe_coupon_id);
            } catch (ApiErrorException $e) {
                report($e);
            }
        }
    }

    private static function isStripeEnabled(): bool
    {
        return config('billing.active_gateway', 'stripe') === 'stripe'
            && !empty(config('billing.stripe.secret'));
    }
}
