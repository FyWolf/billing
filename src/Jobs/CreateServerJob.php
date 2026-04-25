<?php

namespace Fywolf\Billing\Jobs;

use Fywolf\Billing\Events\OrderProvisioning;
use Fywolf\Billing\Models\AuditLog;
use Fywolf\Billing\Models\Order;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CreateServerJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $displayName = 'billing:create-server';

    public int $tries = 3;

    /** @return array<int> */
    public function backoff(): array
    {
        return [60, 120, 300];
    }

    public function __construct(
        public int $orderId
    ) {}

    public function handle(): void
    {
        $order = Order::find($this->orderId);

        if (!$order) {
            // Order was deleted between dispatch and execution — nothing to do
            return;
        }

        if ($order->server) {
            // Already provisioned (e.g. admin created it manually)
            return;
        }

        $results = event(new OrderProvisioning($order));
        if (in_array(false, (array) $results, true)) {
            return;
        }

        if (!$order->packPrice->pack->egg_id) {
            return;
        }

        $order->createServer();
    }

    public function failed(Throwable $exception): void
    {
        $order = Order::find($this->orderId);

        AuditLog::record('server_creation_failed', [
            'order_id' => $this->orderId,
            'error'    => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ], $order);

        // Notify all admin panel users about the failure
        $this->notifyAdmins($exception->getMessage());
    }

    private function notifyAdmins(string $errorMessage): void
    {
        try {
            Notification::make()
                ->title('Server Creation Failed')
                ->body("Order #{$this->orderId} — {$errorMessage}")
                ->danger()
                ->persistent()
                ->broadcast(
                    \App\Models\User::whereHas('roles', fn ($q) => $q->where('is_admin', true))->get()
                );
        } catch (\Exception $e) {
            report($e);
        }
    }
}
