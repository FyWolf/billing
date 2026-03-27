<?php

namespace Fywolf\Billing\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Active = 'active';
    case GracePeriod = 'grace_period';
    case Expired = 'expired';
    case Closed = 'closed';

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::GracePeriod => 'warning',
            self::Expired => 'danger',
            self::Closed => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'tabler-circle-dotted',
            self::Active => 'tabler-circle-check',
            self::GracePeriod => 'tabler-clock-exclamation',
            self::Expired => 'tabler-clock-hour-4',
            self::Closed => 'tabler-circle-x',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::GracePeriod => 'Grace Period',
            self::Expired => 'Expired',
            self::Closed => 'Closed',
        };
    }
}
