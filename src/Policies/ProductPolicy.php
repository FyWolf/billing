<?php

namespace Boy132\Billing\Policies;

use App\Policies\DefaultAdminPolicies;

class ProductPolicy
{
    use DefaultAdminPolicies;

    protected string $modelName = 'product';
}
