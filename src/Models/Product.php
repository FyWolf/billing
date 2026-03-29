<?php

namespace Fywolf\Billing\Models;

use App\Models\Egg;
use Filament\Support\Contracts\HasLabel;
use Fywolf\Billing\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stripe\StripeClient;

/**
 * @property int $id
 * @property ?string $stripe_id
 * @property string $name
 * @property string $description
 * @property ?string $image
 * @property ?string $category
 * @property int $sort_order
 * @property ?int $stock
 * @property bool $is_enabled
 * @property int $cores
 * @property int $memory
 * @property int $disk
 * @property int $swap
 * @property int $io_weight
 * @property array<int|string> $ports
 * @property string[] $tags
 * @property int[]|null $node_ids
 * @property int $allocation_limit
 * @property int $database_limit
 * @property int $backup_limit
 * @property int $egg_id
 * @property Egg $egg
 * @property Collection|ProductPrice[] $prices
 */
class Product extends Model implements HasLabel
{
    use SoftDeletes;

    protected $fillable = [
        'stripe_id',
        'name',
        'description',
        'image',
        'category',
        'sort_order',
        'egg_id',
        'cores',
        'memory',
        'disk',
        'swap',
        'io_weight',
        'ports',
        'tags',
        'node_ids',
        'allocation_limit',
        'database_limit',
        'backup_limit',
        'stock',
        'is_enabled',
    ];

    protected $attributes = [
        'ports'      => '[]',
        'tags'       => '[]',
        'io_weight'  => 500,
        'is_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'ports'      => 'array',
            'tags'       => 'array',
            'node_ids'   => 'array',
            'is_enabled' => 'boolean',
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
                // Archive rather than delete so existing price references remain valid
                $stripeClient->products->update($model->stripe_id, ['active' => false]);
            } catch (\Exception $e) {
                report($e);
            }
        });
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'product_id');
    }

    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class, 'egg_id');
    }

    /**
     * Returns the number of slots still available, or null for unlimited.
     * Counts Active, Pending, and GracePeriod orders against the stock cap.
     */
    public function availableStock(): ?int
    {
        if ($this->stock === null) {
            return null;
        }

        $used = Order::whereHas('productPrice', fn ($q) => $q->where('product_id', $this->id))
            ->whereIn('status', [
                OrderStatus::Active->value,
                OrderStatus::Pending->value,
                OrderStatus::GracePeriod->value,
            ])
            ->count();

        return max(0, $this->stock - $used);
    }

    /**
     * Returns true when the product is enabled and has remaining stock (or unlimited stock).
     */
    public function isAvailable(): bool
    {
        if (!$this->is_enabled) {
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
