<?php

namespace Fywolf\Billing;

use Fywolf\Billing\Contracts\PackProvisionerContract;
use InvalidArgumentException;

class ProvisionerRegistry
{
    /** @var array<string, class-string<PackProvisionerContract>> */
    private array $provisioners = [];

    public function register(string $slug, string $class): void
    {
        $this->provisioners[$slug] = $class;
    }

    public function get(string $slug): PackProvisionerContract
    {
        if (!isset($this->provisioners[$slug])) {
            throw new InvalidArgumentException("Provisioner '{$slug}' is not registered.");
        }

        return app($this->provisioners[$slug]);
    }

    /** @return array<string, string> slug => label */
    public function options(): array
    {
        return array_map(fn (string $class) => $class::getLabel(), $this->provisioners);
    }
}
