<?php

namespace Fywolf\Billing\Events;

use Fywolf\Billing\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

class OrderProvisioning
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
