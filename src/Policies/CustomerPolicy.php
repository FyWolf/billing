<?php

namespace Boy132\Billing\Policies;

use App\Policies\DefaultAdminPolicies;

class CustomerPolicy
{
    use DefaultAdminPolicies;

    protected string $modelName = 'customer';
}
