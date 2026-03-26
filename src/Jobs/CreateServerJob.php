<?php

namespace Boy132\Billing\Jobs;

use Boy132\Billing\Models\AuditLog;
use Boy132\Billing\Models\Order;
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

    /**
     * The display name of the job (helps identify it in queue monitoring).
     */
    public string $displayName = 'billing:create-server';

    /**
     * Number of attempts before the job is considered failed.
     * Each retry waits for the backoff period below.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before each retry: 60s, then 120s, then 300s.
     *
     * @return array<int>
     */
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

        $order->createServer();
    }

    /**
     * Called when all retry attempts have been exhausted.
     */
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
            // Filament's database notifications target admin users.
            // We broadcast to the admin channel so any online admin sees it.
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
