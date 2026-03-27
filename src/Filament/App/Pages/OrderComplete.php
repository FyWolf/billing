<?php

namespace Fywolf\Billing\Filament\App\Pages;

use Fywolf\Billing\Models\Customer;
use Fywolf\Billing\Models\Order;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class OrderComplete extends Page
{
    protected string $view = 'billing::order-complete';

    protected static ?string $slug = 'order-complete';

    protected static bool $shouldRegisterNavigation = false;

    public ?Order $order = null;

    public function mount(): void
    {
        $token = request()->query('token');

        if (!$token) {
            abort(404);
        }

        $order = Order::findByConfirmationToken($token);

        if (!$order) {
            abort(404);
        }

        $customer = Customer::where('user_id', auth()->id())->first();

        if (!$customer || $order->customer_id !== $customer->id) {
            abort(404);
        }

        $this->order = $order->load(['productPrice.product', 'customer.user']);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Order Complete';
    }
}
