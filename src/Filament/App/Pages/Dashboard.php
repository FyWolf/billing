<?php

namespace Fywolf\Billing\Filament\App\Pages;

use Fywolf\Billing\Filament\App\Widgets\CategoryWidget;
use Fywolf\Billing\Filament\App\Widgets\MyServersWidget;
use Fywolf\Billing\Filament\App\Widgets\ProductWidget;
use Fywolf\Billing\Filament\App\Widgets\WelcomeWidget;
use Fywolf\Billing\Models\Product;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        $widgets = [
            new WidgetConfiguration(WelcomeWidget::class),
            new WidgetConfiguration(MyServersWidget::class),
        ];

        $products = Product::with('prices')
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $product) => $product->prices->isNotEmpty());

        $grouped = $products->groupBy(fn (Product $product) => $product->category ?? '');

        foreach ($grouped as $category => $categoryProducts) {
            $widgets[] = new WidgetConfiguration(CategoryWidget::class, [
                'categoryName' => $category ?: 'Other Products',
            ]);

            foreach ($categoryProducts as $product) {
                $widgets[] = new WidgetConfiguration(ProductWidget::class, ['product' => $product]);
            }
        }

        return $widgets;
    }
}
