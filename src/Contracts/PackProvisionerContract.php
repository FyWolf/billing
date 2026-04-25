<?php

namespace Fywolf\Billing\Contracts;

use Fywolf\Billing\Models\Order;

interface PackProvisionerContract
{
    public static function getSlug(): string;

    public static function getLabel(): string;

    public function isProvisioned(Order $order): bool;

    public function provision(Order $order): void;

    public function suspend(Order $order): void;

    public function unsuspend(Order $order): void;

    public function terminate(Order $order): void;

    public function getManagementUrl(Order $order): ?string;
}
