<?php

namespace Fywolf\Billing\Policies;

use App\Policies\DefaultAdminPolicies;

class CustomerPolicy
{
    use DefaultAdminPolicies;

    protected string $modelName = 'customer';
}
