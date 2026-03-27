<?php

namespace Fywolf\Billing\Filament\App\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class CategoryWidget extends Widget
{
    protected string $view = 'billing::category-header'; // @phpstan-ignore property.defaultValue

    protected int|string|array $columnSpan = 'full';

    public ?string $categoryName = null;

    public function mount(?string $categoryName = null): void
    {
        $this->categoryName = $categoryName;
    }
}
