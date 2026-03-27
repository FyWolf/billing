<?php

namespace Fywolf\Billing\Enums;

use Filament\Support\Contracts\HasLabel;

enum CouponType: string implements HasLabel
{
    case Amount = 'amount';
    case Percentage = 'percentage';

    public function getLabel(): string
    {
        return $this->name;
    }
}
