<?php

namespace Fywolf\Billing\Http\Middleware;

use App\Livewire\AlertBanner;
use Closure;
use Filament\Facades\Filament;
use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Models\Order;
use Illuminate\Http\Request;

class CancellationWarningMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $server = Filament::getTenant();

        if ($server) {
            $order = Order::where('server_id', $server->id)
                ->where('status', OrderStatus::Cancelled)
                ->first();

            if ($order && $order->expires_at) {
                AlertBanner::make('cancellation_warning_' . $order->id)
                    ->title('This server is scheduled for suspension')
                    ->body('Your subscription has been cancelled. This server will be suspended on ' . $order->expires_at->format('M j, Y') . '.')
                    ->status('warning')
                    ->send();
            }
        }

        return $next($request);
    }
}
