<?php

namespace Boy132\Billing\Http\Controllers\Api;

use App\Filament\Server\Pages\Console;
use App\Http\Controllers\Controller;
use Boy132\Billing\Enums\OrderStatus;
use Boy132\Billing\Filament\App\Resources\Orders\Pages\ListOrders;
use Boy132\Billing\Models\AuditLog;
use Boy132\Billing\Models\Customer;
use Boy132\Billing\Models\Order;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

class CheckoutController extends Controller
{
    public function __construct(
        private StripeClient $stripeClient
    ) {}

    public function success(Request $request): RedirectResponse
    {
        $sessionId = $request->get('session_id');

        if ($sessionId === null) {
            return redirect(Filament::getPanel('app')->getUrl());
        }

        $session = $this->stripeClient->checkout->sessions->retrieve($sessionId);

        if ($session->payment_status === Session::PAYMENT_STATUS_UNPAID) {
            return redirect(ListOrders::getUrl(panel: 'app'));
        }

        // Ownership check: this session must belong to the currently authenticated user
        $customer = Customer::where('user_id', auth()->id())->first();

        if (!$customer) {
            return redirect(ListOrders::getUrl(panel: 'app'));
        }

        /** @var ?Order $order */
        $order = Order::where('stripe_checkout_id', $session->id)
            ->where('customer_id', $customer->id) // ensures caller owns this session
            ->first();

        if (!$order) {
            return redirect(ListOrders::getUrl(panel: 'app'));
        }

        // Idempotent: if the webhook already activated the order, just redirect
        if ($order->status === OrderStatus::Active) {
            return $order->server
                ? redirect(Console::getUrl(panel: 'server', tenant: $order->server))
                : redirect(ListOrders::getUrl(panel: 'app'));
        }

        $order->activate($session->payment_intent);
        $order->refresh();

        AuditLog::record('stripe_redirect_payment_confirmed', [
            'stripe_session_id'     => $session->id,
            'stripe_payment_intent' => $session->payment_intent,
        ], $order);

        return $order->server
            ? redirect(Console::getUrl(panel: 'server', tenant: $order->server))
            : redirect(ListOrders::getUrl(panel: 'app'));
    }

    public function cancel(Request $request): RedirectResponse
    {
        $sessionId = $request->get('session_id');

        if ($sessionId) {
            $customer = Customer::where('user_id', auth()->id())->first();

            /** @var ?Order $order */
            $order = Order::where('stripe_checkout_id', $sessionId)
                ->where('customer_id', $customer?->id)
                ->first();

            if ($order) {
                $order->close();
                AuditLog::record('stripe_checkout_cancelled', [], $order);
            }
        }

        return redirect(ListOrders::getUrl(panel: 'app'));
    }
}
