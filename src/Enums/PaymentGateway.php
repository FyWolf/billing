<?php

namespace Boy132\Billing\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentGateway: string implements HasColor, HasIcon, HasLabel
{
    case Stripe = 'stripe';
    case PayPal = 'paypal';
    case Trial = 'trial';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe',
            self::PayPal => 'PayPal',
            self::Trial => 'Trial',
            self::Manual => 'Manual',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Stripe => 'indigo',
            self::PayPal => 'info',
            self::Trial => 'success',
            self::Manual => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Stripe => 'tabler-brand-stripe',
            self::PayPal => 'tabler-brand-paypal',
            self::Trial => 'tabler-clock',
            self::Manual => 'tabler-user-shield',
        };
    }
}
