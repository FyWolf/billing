<?php

namespace Fywolf\Billing\Models;

use App\Models\Egg;
use Filament\Support\Contracts\HasLabel;
use Fywolf\Billing\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stripe\StripeClient;

/**
 * @property int $id
 * @property ?string $stripe_id
 * @property string $name
 * @property ?string $description
 * @property ?string $image
 * @property int $sort_order
 * @property bool $is_enabled
 * @property bool $visible_in_store
 * @property ?int $stock
 * @property bool $force_out_of_stock
 * @property array<int|string> $ports
 * @property string[] $tags
 * @property int[]|null $node_ids
 * @property ?int $egg_id
 * @property int $product_id
 * @property ?Egg $egg
 * @property Product $product
 * @property Collection|PackPrice[] $prices
 * @property Collection|PackExpansion[] $packExpansions
 */
class Pack extends Model implements HasLabel
{
    use SoftDeletes;

    protected $table = 'billing_packs';

    protected $fillable = [
        'stripe_id',
        'name',
        'description',
        'image',
        'sort_order',
        'is_enabled',
        'visible_in_store',
        'provisioner',
        'stock',
        'force_out_of_stock',
        'ports',
        'tags',
        'node_ids',
        'egg_id',
        'product_id',
    ];

    protected $attributes = [
        'ports'              => '[]',
        'tags'               => '[]',
        'is_enabled'         => true,
        'visible_in_store'   => true,
        'provisioner'        => 'wings',
        'force_out_of_stock' => false,
    ];

    protected function casts(): array
    {
        return [
            'ports'              => 'array',
            'tags'               => 'array',
            'node_ids'           => 'array',
            'is_enabled'         => 'boolean',
            'visible_in_store'   => 'boolean',
            'force_out_of_stock' => 'boolean',
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

        static::deleted(function (self $model) {
            if (!self::isStripeEnabled() || is_null($model->stripe_id)) {
                return;
            }

            try {
                /** @var StripeClient $stripeClient */
                $stripeClient = app(StripeClient::class);
                $stripeClient->products->update($model->stripe_id, ['active' => false]);
            } catch (\Exception $e) {
                report($e);
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PackPrice::class, 'pack_id');
    }

    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class, 'egg_id');
    }

    public function packExpansions(): HasMany
    {
        return $this->hasMany(PackExpansion::class, 'pack_id');
    }

    public function activeOrders(): HasManyThrough
    {
        return $this->hasManyThrough(
            Order::class,
            PackPrice::class,
            'pack_id',
            'pack_price_id',
            'id',
            'id'
        )->whereIn('billing_orders.status', [
            OrderStatus::Active->value,
            OrderStatus::Pending->value,
            OrderStatus::GracePeriod->value,
        ]);
    }

    public function availableStock(): ?int
    {
        if ($this->stock === null) {
            return null;
        }

        $used = array_key_exists('active_orders_count', $this->attributes)
            ? (int) $this->attributes['active_orders_count']
            : Order::whereHas('packPrice', fn ($q) => $q->where('pack_id', $this->id))
                ->whereIn('status', [
                    OrderStatus::Active->value,
                    OrderStatus::Pending->value,
                    OrderStatus::GracePeriod->value,
                ])
                ->count();

        return max(0, $this->stock - $used);
    }

    /**
     * Returns true when the pack is available for purchase.
     * Respects is_enabled, force_out_of_stock, and actual stock.
     */
    public function isAvailable(): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        if ($this->force_out_of_stock) {
            return false;
        }

        $available = $this->availableStock();

        return $available === null || $available > 0;
    }

    public function getLabel(): string
    {
        return $this->name;
    }

    public function sync(): void
    {
        if (!self::isStripeEnabled()) {
            return;
        }

        /** @var StripeClient $stripeClient */
        $stripeClient = app(StripeClient::class);

        try {
            if (is_null($this->stripe_id)) {
                $stripeProduct = $stripeClient->products->create([
                    'name'        => $this->name,
                    'description' => $this->description,
                ]);

                $this->updateQuietly(['stripe_id' => $stripeProduct->id]);
            } else {
                $stripeClient->products->update($this->stripe_id, [
                    'name'        => $this->name,
                    'description' => $this->description,
                ]);
            }
        } catch (\Exception $e) {
            report($e);
        }
    }

    private static function isStripeEnabled(): bool
    {
        return !empty(config('billing.stripe.secret'));
    }
}
