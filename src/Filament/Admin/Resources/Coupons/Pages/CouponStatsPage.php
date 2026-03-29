<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Coupons\Pages;

use Carbon\Carbon;
use Fywolf\Billing\Filament\Admin\Resources\Coupons\CouponResource;
use Fywolf\Billing\Models\Coupon;
use Fywolf\Billing\Models\Order;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use NumberFormatter;

class CouponStatsPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CouponResource::class;

    protected static string $view = 'billing::admin.coupon-stats';

    // ── KPI scalars ──────────────────────────────────────────────────────────

    public int   $totalUses      = 0;
    public float $revenueSaved   = 0.0;
    public int   $usesThisMonth  = 0;
    public ?int  $remaining      = null;
    public float $avgDiscount    = 0.0;
    public int   $maxRedemptions = 0;
    public string $currency      = 'USD';

    // ── Chart payloads (JSON) ─────────────────────────────────────────────────

    public string $usageChartJson   = '{}';
    public string $productChartJson = '{}';
    public string $statusChartJson  = '{}';

    // ── Recent orders table ───────────────────────────────────────────────────

    public array $recentOrders = [];

    // ─────────────────────────────────────────────────────────────────────────

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        /** @var Coupon $coupon */
        $coupon = $this->getRecord();

        $this->currency      = (string) config('billing.currency', 'USD');
        $this->maxRedemptions = (int) ($coupon->max_redemptions ?? 0);

        $orders = Order::where('coupon_id', $coupon->id)
            ->with(['productPrice.product', 'customer'])
            ->get();

        // ── KPI ──────────────────────────────────────────────────────────────

        $this->totalUses     = $orders->count();
        $this->usesThisMonth = $orders->filter(
            fn (Order $o) => $o->created_at->isCurrentMonth()
        )->count();

        $this->revenueSaved = (float) $orders->sum(
            fn (Order $o) => $coupon->calculateDiscount((float) $o->productPrice->cost)
        );

        $this->avgDiscount = $this->totalUses > 0
            ? round($this->revenueSaved / $this->totalUses, 2)
            : 0.0;

        $this->remaining = $coupon->max_redemptions !== null
            ? max(0, $coupon->max_redemptions - $this->totalUses)
            : null;

        // ── Usage over last 30 days ───────────────────────────────────────────

        $days = [];
        for ($i = 29; $i >= 0; $i--) {
            $days[now()->subDays($i)->format('Y-m-d')] = 0;
        }

        foreach ($orders as $order) {
            $key = $order->created_at->format('Y-m-d');
            if (array_key_exists($key, $days)) {
                $days[$key]++;
            }
        }

        $this->usageChartJson = json_encode([
            'labels' => array_map(
                fn ($d) => Carbon::parse($d)->format('M j'),
                array_keys($days)
            ),
            'data' => array_values($days),
        ]);

        // ── Product breakdown ─────────────────────────────────────────────────

        $productCounts = $orders
            ->groupBy(fn (Order $o) => $o->productPrice->product->name ?? 'Unknown')
            ->map->count()
            ->sortDesc();

        $palette = ['#6366f1', '#8b5cf6', '#ec4899', '#14b8a6', '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#a855f7', '#f97316'];

        $this->productChartJson = json_encode([
            'labels' => $productCounts->keys()->values()->toArray(),
            'data'   => $productCounts->values()->toArray(),
            'colors' => array_slice($palette, 0, $productCounts->count()),
        ]);

        // ── Status breakdown ──────────────────────────────────────────────────

        $statusColors = [
            'Active'       => '#10b981',
            'Pending'      => '#f59e0b',
            'Grace Period' => '#f97316',
            'Cancelled'    => '#f97316',
            'Expired'      => '#ef4444',
            'Closed'       => '#6b7280',
        ];

        $statusCounts = $orders
            ->groupBy(fn (Order $o) => $o->status->getLabel())
            ->map->count()
            ->sortDesc();

        $this->statusChartJson = json_encode([
            'labels' => $statusCounts->keys()->values()->toArray(),
            'data'   => $statusCounts->values()->toArray(),
            'colors' => $statusCounts->keys()->map(
                fn ($label) => $statusColors[$label] ?? '#6b7280'
            )->values()->toArray(),
        ]);

        // ── Recent orders ─────────────────────────────────────────────────────

        $fmt = new NumberFormatter(auth()->user()->language, NumberFormatter::CURRENCY);

        $this->recentOrders = $orders
            ->sortByDesc('created_at')
            ->take(15)
            ->map(fn (Order $o) => [
                'id'       => $o->id,
                'customer' => $o->customer->first_name . ' ' . $o->customer->last_name,
                'product'  => ($o->productPrice->product->name ?? '—') . ' — ' . $o->productPrice->name,
                'price'    => $fmt->formatCurrency((float) $o->productPrice->cost, $this->currency),
                'discount' => $fmt->formatCurrency($coupon->calculateDiscount((float) $o->productPrice->cost), $this->currency),
                'status'   => $o->status->getLabel(),
                'color'    => $o->status->getColor(),
                'date'     => $o->created_at->format('M j, Y'),
            ])
            ->values()
            ->toArray();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Edit Coupon')
                ->icon('tabler-edit')
                ->url(CouponResource::getUrl('edit', ['record' => $this->getRecord()])),
        ];
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name . ' — Stats';
    }

    public function getBreadcrumb(): string
    {
        return 'Stats';
    }
}
