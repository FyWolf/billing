<?php

namespace Boy132\Billing\Models;

use App\Models\User;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property int $balance
 * @property int $user_id
 * @property User $user
 * @property Collection|Order[] $orders
 */
class Customer extends Model implements HasLabel
{
    protected $fillable = [
        'first_name',
        'last_name',
        'balance',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->BelongsTo(User::class, 'user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function getLabel(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}
