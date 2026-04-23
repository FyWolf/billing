<?php

namespace Fywolf\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NumberFormatter;

/**
 * @property int $id
 * @property int $pack_id
 * @property int $expansion_id
 * @property ?float $custom_price
 * @property bool $is_enabled
 * @property Pack $pack
 * @property Expansion $expansion
 */
class PackExpansion extends Model
{
    protected $table = 'billing_pack_expansions';

    protected $fillable = [
        'pack_id',
        'expansion_id',
        'custom_price',
        'is_enabled',
    ];

    protected $attributes = [
        'is_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'custom_price' => 'float',
            'is_enabled'   => 'boolean',
        ];
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::class, 'pack_id');
    }

    public function expansion(): BelongsTo
    {
        return $this->belongsTo(Expansion::class, 'expansion_id');
    }

    public function orderExpansions(): HasMany
    {
        return $this->hasMany(OrderExpansion::class, 'pack_expansion_id');
    }

    public function effectivePrice(): float
    {
        return $this->custom_price ?? $this->expansion->cost;
    }

    public function formatEffectivePrice(): string
    {
        $cost = $this->effectivePrice();

        if ($cost === 0.0) {
            return 'Free';
        }

        $locale    = auth()->user()?->language ?? 'en';
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($cost, config('billing.currency'));
    }
}
