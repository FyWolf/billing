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

        $products = Product::with(['packs.prices', 'packs.packExpansions.expansion'])
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($products as $product) {
            $packs = $product->packs
                ->filter(fn ($pack) => $pack->prices->isNotEmpty() && $pack->visible_in_store)
                ->sortBy('sort_order')
                ->values();

            if ($packs->isEmpty()) {
                continue;
            }

            $widgets[] = new WidgetConfiguration(CategoryWidget::class, [
                'categoryName' => $product->name,
            ]);

            foreach ($packs as $pack) {
                $widgets[] = new WidgetConfiguration(ProductWidget::class, ['product' => $pack]);
            }
        }

        return $widgets;
    }
}
