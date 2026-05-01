<?php

namespace Fywolf\Billing\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Fywolf\Billing\Models\Pack;
use Fywolf\Billing\Models\Product;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $products = Product::with(['packs.prices', 'packs.packExpansions.expansion'])
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories = $products->map(function (Product $product) {
            $packs = $product->packs
                ->filter(fn (Pack $pack) => $pack->prices->isNotEmpty()
                    && $pack->visible_in_store
                    && $pack->is_enabled)
                ->sortBy('sort_order')
                ->values()
                ->map(fn (Pack $pack) => $this->formatPack($pack));

            if ($packs->isEmpty()) {
                return null;
            }

            return [
                'id'    => $product->id,
                'name'  => $product->name,
                'packs' => $packs,
            ];
        })->filter()->values();

        return response()->json([
            'currency'   => config('billing.currency', 'USD'),
            'categories' => $categories,
        ]);
    }

    private function formatPack(Pack $pack): array
    {
        $expansions = $pack->packExpansions
            ->filter(fn ($pe) => $pe->is_enabled && $pe->expansion->isAvailable())
            ->values()
            ->map(fn ($pe) => [
                'id'                      => $pe->expansion->id,
                'pack_expansion_id'       => $pe->id,
                'name'                    => $pe->expansion->name,
                'description'             => $pe->expansion->description,
                'cores_boost'             => $pe->expansion->cores_boost,
                'memory_boost'            => $pe->expansion->memory_boost,
                'disk_boost'              => $pe->expansion->disk_boost,
                'swap_boost'              => $pe->expansion->swap_boost,
                'allocation_limit_boost'  => $pe->expansion->allocation_limit_boost,
                'database_limit_boost'    => $pe->expansion->database_limit_boost,
                'backup_limit_boost'      => $pe->expansion->backup_limit_boost,
                'cost'                    => $pe->effectivePrice(),
                'is_available'            => $pe->expansion->isAvailable(),
                'stock_available'         => $pe->expansion->availableStock(),
            ]);

        return [
            'id'              => $pack->id,
            'name'            => $pack->name,
            'description'     => $pack->description,
            'image'           => $pack->image,
            'is_available'    => $pack->isAvailable(),
            'stock_available' => $pack->availableStock(),
            'prices'          => $pack->prices->map(fn ($price) => [
                'id'               => $price->id,
                'name'             => $price->name,
                'cost'             => $price->cost,
                'interval_type'    => $price->interval_type->value,
                'interval_value'   => $price->interval_value,
                'renewable'        => $price->renewable,
                'trial_days'       => $price->trial_days,
                'cores'            => $price->cores,
                'memory'           => $price->memory,
                'disk'             => $price->disk,
                'backup_limit'     => $price->backup_limit,
                'database_limit'   => $price->database_limit,
                'allocation_limit' => $price->allocation_limit,
            ])->values(),
            'expansions' => $expansions,
        ];
    }
}
