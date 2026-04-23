<?php

namespace Fywolf\Billing\Models;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property ?string $description
 * @property ?string $image
 * @property int $sort_order
 * @property bool $is_enabled
 * @property Collection|Pack[] $packs
 */
class Product extends Model implements HasLabel
{
    protected $table = 'billing_products';

    protected $fillable = [
        'name',
        'description',
        'image',
        'sort_order',
        'is_enabled',
    ];

    protected $attributes = [
        'sort_order' => 0,
        'is_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function packs(): HasMany
    {
        return $this->hasMany(Pack::class, 'product_id');
    }

    public function getLabel(): string
    {
        return $this->name;
    }
}
