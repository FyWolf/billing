<?php

namespace Fywolf\Billing\Console\Commands;

use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Models\Order;
use Illuminate\Console\Command;

class CheckOrdersCommand extends Command
{
    protected $signature = 'p:billing:check-orders';

    protected $description = 'Process order expirations and grace periods.';

    public function handle(): int
    {
        $this->cleanupStalePendingOrders();
        $this->processExpirations();

        return 0;
    }

    /**
     * Move Active orders that have hit their expiry into GracePeriod (or expire
     * them immediately if grace_period_hours is 0), and expire GracePeriod orders
     * whose grace window has been exhausted.
     *
     * Uses cursor() to avoid loading every order into memory at once.
     */
    private function processExpirations(): void
    {
        $graceHours = (int) config('billing.grace_period_hours', 24);

        // -- Phase 1: Active → GracePeriod (or directly Expired if grace = 0) --
        Order::where('status', OrderStatus::Active->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now('UTC'))
            ->cursor()
            ->each(function (Order $order) use ($graceHours) {
                if ($graceHours > 0) {
                    $order->enterGracePeriod();
                    $this->line("Order #{$order->id} entered grace period.");
                } else {
                    $order->expire();
                    $this->line("Order #{$order->id} expired (no grace period).");
                }
            });

        if ($graceHours <= 0) {
            return;
        }

        // -- Phase 2: GracePeriod → Expired (grace window exhausted) --
        Order::where('status', OrderStatus::GracePeriod->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now('UTC')->subHours($graceHours))
            ->cursor()
            ->each(function (Order $order) {
                $order->expire();
                $this->line("Order #{$order->id} expired after grace period.");
            });
    }

    /**
     * Close Pending orders that have been sitting for more than 2 hours with no
     * payment attempt. Prevents accumulation of abandoned checkout sessions.
     */
    private function cleanupStalePendingOrders(): void
    {
        Order::where('status', OrderStatus::Pending->value)
            ->where('created_at', '<=', now('UTC')->subHours(2))
            ->cursor()
            ->each(function (Order $order) {
                $order->close();
                $this->line("Order #{$order->id} closed (stale Pending).");
            });
    }
}
