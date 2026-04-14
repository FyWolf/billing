<?php

namespace Fywolf\Billing\Models;

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
 * @property ?string $company_name
 * @property ?string $address
 * @property ?string $address2
 * @property ?string $city
 * @property ?string $zip
 * @property ?string $country
 * @property ?string $vat_number
 * @property ?string $siret
 * @property int $balance
 * @property int $user_id
 * @property ?string $stripe_customer_id
 * @property User $user
 * @property Collection|Order[] $orders
 */
class Customer extends Model implements HasLabel
{
    protected $fillable = [
        'first_name',
        'last_name',
        'company_name',
        'address',
        'address2',
        'city',
        'zip',
        'country',
        'vat_number',
        'siret',
        'balance',
        'user_id',
        'stripe_customer_id',
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
