<?php

namespace Fywolf\Billing\Policies;

use App\Policies\DefaultAdminPolicies;

class ProductPolicy
{
    use DefaultAdminPolicies;

    protected string $modelName = 'product';
}
