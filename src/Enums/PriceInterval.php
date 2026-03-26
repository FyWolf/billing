<?php

namespace Boy132\Billing\Enums;

use Filament\Support\Contracts\HasLabel;

enum PriceInterval: string implements HasLabel
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    public function getLabel(): string
    {
        return $this->name;
    }
}
