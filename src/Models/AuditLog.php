<?php

namespace Fywolf\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Request;

/**
 * @property int $id
 * @property string $action
 * @property array|null $metadata
 * @property string|null $ip_address
 * @property int|null $order_id
 * @property int|null $customer_id
 * @property int|null $user_id
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'billing_audit_logs';

    protected $fillable = [
        'action',
        'metadata',
        'ip_address',
        'order_id',
        'customer_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Record a billing audit event.
     *
     * @param  string  $action   e.g. 'order_activated', 'server_created', 'payment_received'
     * @param  array   $metadata Additional context (amounts, gateway, error messages, etc.)
     * @param  Order|null $order  The related order, if any
     */
    public static function record(string $action, array $metadata = [], ?Order $order = null): self
    {
        return static::create([
            'action'      => $action,
            'metadata'    => $metadata ?: null,
            'ip_address'  => Request::ip(),
            'order_id'    => $order?->id,
            'customer_id' => $order?->customer_id,
            'user_id'     => auth()->id(),
        ]);
    }
}
