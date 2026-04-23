<?php

namespace Fywolf\Billing\Models;

use Filament\Support\Contracts\HasLabel;
use Fywolf\Billing\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stripe\StripeClient;

/**
 * @property int $id
 * @property ?string $stripe_id
 * @property string $name
 * @property ?string $description
 * @property ?string $image
 * @property int $cores_boost
 * @property int $memory_boost
 * @property int $disk_boost
 * @property int $swap_boost
 * @property int $allocation_limit_boost
 * @property int $database_limit_boost
 * @property int $backup_limit_boost
 * @property float $cost
 * @property bool $is_enabled
 * @property bool $force_out_of_stock
 * @property ?int $stock
 * @property Collection|PackExpansion[] $packExpansions
 */
class Expansion extends Model implements HasLabel
{
    use SoftDeletes;

    protected $table = 'billing_expansions';

    protected $fillable = [
        'stripe_id',
        'name',
        'description',
        'image',
        'cores_boost',
        'memory_boost',
        'disk_boost',
        'swap_boost',
        'allocation_limit_boost',
        'database_limit_boost',
        'backup_limit_boost',
        'cost',
        'is_enabled',
        'force_out_of_stock',
        'stock',
    ];

    protected $attributes = [
        'is_enabled'         => true,
        'force_out_of_stock' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_enabled'         => 'boolean',
            'force_out_of_stock' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::created(function (self $model) {
            $model->syncStripe();
        });

        static::updated(function (self $model) {
            $model->syncStripe();
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

    public function packExpansions(): HasMany
    {
        return $this->hasMany(PackExpansion::class, 'expansion_id');
    }

    /**
     * Returns remaining stock, or null for unlimited.
     */
    public function availableStock(): ?int
    {
        if ($this->stock === null) {
            return null;
        }

        $used = OrderExpansion::whereHas('packExpansion', fn ($q) => $q->where('expansion_id', $this->id))
            ->whereHas('order', fn ($q) => $q->whereIn('status', [
                OrderStatus::Active->value,
                OrderStatus::Pending->value,
                OrderStatus::GracePeriod->value,
            ]))
            ->count();

        return max(0, $this->stock - $used);
    }

    /**
     * Returns true when this expansion is available for purchase.
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

    /**
     * Returns a short summary of what this expansion boosts, e.g. "+2 Cores, +50 GB Disk"
     */
    public function boostSummary(): string
    {
        $unit  = config('panel.use_binary_prefix') ? 'MiB' : 'MB';
        $parts = [];

        if ($this->cores_boost)            $parts[] = "+{$this->cores_boost} Core" . ($this->cores_boost > 1 ? 's' : '');
        if ($this->memory_boost)           $parts[] = "+{$this->memory_boost} {$unit} RAM";
        if ($this->disk_boost)             $parts[] = "+{$this->disk_boost} {$unit} Disk";
        if ($this->swap_boost)             $parts[] = "+{$this->swap_boost} {$unit} Swap";
        if ($this->allocation_limit_boost) $parts[] = "+{$this->allocation_limit_boost} Allocations";
        if ($this->database_limit_boost)   $parts[] = "+{$this->database_limit_boost} Databases";
        if ($this->backup_limit_boost)     $parts[] = "+{$this->backup_limit_boost} Backups";

        return implode(', ', $parts) ?: 'No boosts configured';
    }

    public function getLabel(): string
    {
        return $this->name;
    }

    public function syncStripe(): void
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
