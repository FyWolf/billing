<?php

namespace Fywolf\Billing\Filament\App\Widgets;

use App\Filament\Server\Pages\Console;
use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Models\Customer;
use Fywolf\Billing\Models\Order;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class MyServersWidget extends Widget
{
    protected string $view = 'billing::my-servers'; // @phpstan-ignore property.defaultValue

    protected int|string|array $columnSpan = 'full';

    public function getServers(): Collection
    {
        $customer = Customer::where('user_id', auth()->id())->first();

        if (!$customer) {
            return collect();
        }

        return Order::where('customer_id', $customer->id)
            ->whereIn('status', [OrderStatus::Active, OrderStatus::GracePeriod])
            ->whereNotNull('server_id')
            ->with(['server', 'productPrice.product'])
            ->get()
            ->filter(fn (Order $order) => $order->server !== null);
    }

    public function getServerUrl(Order $order): string
    {
        return Console::getUrl(panel: 'server', tenant: $order->server);
    }
}
