<?php

namespace Fywolf\Billing\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Fywolf\Billing\Models\Product;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $products = Product::with('prices')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $p) => $p->prices->isNotEmpty());

        $grouped = $products->groupBy(fn (Product $p) => $p->category ?? 'Other');

        $categories = $grouped->map(function ($products, $category) {
            return [
                'name'     => $category,
                'products' => $products->map(fn (Product $product) => [
                    'id'             => $product->id,
                    'name'           => $product->name,
                    'description'    => $product->description,
                    'image'          => $product->image,
                    'cores'          => $product->cores,
                    'memory'         => $product->memory,
                    'disk'           => $product->disk,
                    'backup_limit'   => $product->backup_limit,
                    'is_enabled'      => $product->is_enabled,
                    'stock_available' => $product->availableStock(),
                    'prices'         => $product->prices->map(fn ($price) => [
                        'id'             => $price->id,
                        'name'           => $price->name,
                        'cost'           => $price->cost,
                        'interval_type'  => $price->interval_type->value,
                        'interval_value' => $price->interval_value,
                        'renewable'      => $price->renewable,
                    ])->values(),
                ])->values(),
            ];
        })->values();

        return response()->json([
            'currency'   => config('billing.currency', 'USD'),
            'categories' => $categories,
        ]);
    }
}
