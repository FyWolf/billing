<?php

namespace Fywolf\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int $pack_expansion_id
 * @property float $price_paid
 * @property Order $order
 * @property PackExpansion $packExpansion
 */
class OrderExpansion extends Model
{
    protected $table = 'billing_order_expansions';

    protected $fillable = [
        'order_id',
        'pack_expansion_id',
        'price_paid',
    ];

    protected function casts(): array
    {
        return [
            'price_paid' => 'float',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function packExpansion(): BelongsTo
    {
        return $this->belongsTo(PackExpansion::class, 'pack_expansion_id');
    }
}
