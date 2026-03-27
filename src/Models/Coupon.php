<?php

namespace Fywolf\Billing\Models;

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
            $model->deleteStripeCoupon();
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
                $this->createStripeCoupon($stripeClient);
            } else {
                $stripeCoupon = $stripeClient->coupons->retrieve($this->stripe_coupon_id);

                $changed = $stripeCoupon->amount_off !== $this->amount_off
                    || $stripeCoupon->percent_off !== $this->percent_off
                    || $stripeCoupon->max_redemptions !== $this->max_redemptions
                    || $stripeCoupon->redeem_by !== ($this->redeem_by ? $this->redeem_by->timestamp : null);

                if ($changed) {
                    $this->deleteStripeCoupon();
                    $this->updateQuietly([
                        'stripe_coupon_id'    => null,
                        'stripe_promotion_id' => null,
                    ]);
                    $this->refresh();
                    $this->createStripeCoupon($stripeClient);
                }
            }
        } catch (ApiErrorException $e) {
            report($e);
        }
    }

    private function createStripeCoupon(StripeClient $stripeClient): void
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

        $this->updateQuietly([
            'stripe_coupon_id' => $stripeCoupon->id,
        ]);
    }

    private function deleteStripeCoupon(): void
    {
        if (!self::isStripeEnabled()) {
            return;
        }

        /** @var StripeClient $stripeClient */
        $stripeClient = app(StripeClient::class);

        if (!is_null($this->stripe_coupon_id)) {
            try {
                $stripeClient->coupons->delete($this->stripe_coupon_id);
            } catch (ApiErrorException $e) {
                report($e);
            }
        }
    }

    /**
     * Find a valid coupon by its code.
     */
    public static function findByCode(string $code): ?self
    {
        $coupon = static::where('code', $code)->first();

        if (!$coupon) {
            return null;
        }

        if ($coupon->redeem_by && $coupon->redeem_by->isPast()) {
            return null;
        }

        return $coupon;
    }

    /**
     * Calculate the discount amount for a given price.
     */
    public function calculateDiscount(float $price): float
    {
        if ($this->amount_off) {
            return min($this->amount_off, $price);
        }

        if ($this->percent_off) {
            return round($price * ($this->percent_off / 100), 2);
        }

        return 0;
    }

    private static function isStripeEnabled(): bool
    {
        return !empty(config('billing.stripe.secret'));
    }
}
