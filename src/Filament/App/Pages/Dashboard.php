<?php

namespace Boy132\Billing\Filament\App\Pages;

use Boy132\Billing\Filament\App\Widgets\ProductWidget;
use Boy132\Billing\Filament\App\Widgets\WelcomeWidget;
use Boy132\Billing\Models\Product;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        $widgets = [new WidgetConfiguration(WelcomeWidget::class)];

        // Eager-load prices to avoid N+1 on the prices->count() and price->getLabel() calls
        $products = Product::with('prices')->get();

        foreach ($products as $product) {
            if ($product->prices->isEmpty()) {
                continue;
            }

            $widgets[] = new WidgetConfiguration(ProductWidget::class, ['product' => $product]);
        }

        return $widgets;
    }
}
