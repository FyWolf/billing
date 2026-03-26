<?php

namespace Boy132\Billing\Policies;

use App\Policies\DefaultAdminPolicies;

class OrderPolicy
{
    use DefaultAdminPolicies;

    protected string $modelName = 'order';
}
