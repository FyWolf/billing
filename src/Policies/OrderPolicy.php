<?php

namespace Fywolf\Billing\Policies;

use App\Policies\DefaultAdminPolicies;

class OrderPolicy
{
    use DefaultAdminPolicies;

    protected string $modelName = 'order';
}
