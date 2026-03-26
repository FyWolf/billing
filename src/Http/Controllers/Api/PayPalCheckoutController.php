<?php

namespace Boy132\Billing\Http\Controllers\Api;

use App\Filament\Server\Pages\Console;
use App\Http\Controllers\Controller;
use Boy132\Billing\Enums\OrderStatus;
use Boy132\Billing\Filament\App\Resources\Orders\Pages\ListOrders;
use Boy132\Billing\Models\AuditLog;
use Boy132\Billing\Models\Customer;
use Boy132\Billing\Models\Order;
use Boy132\Billing\Services\PayPalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

class PayPalCheckoutController extends Controller
{
    public function __construct(
        private PayPalService $payPalService
    ) {}

    /**
     * PayPal redirects here with ?token=PAYPAL_ORDER_ID&PayerID=... after buyer approves.
     */
    public function success(Request $request): RedirectResponse
    {
        $token = $request->get('token'); // This is the PayPal order ID

        if (!$token) {
            return redirect(ListOrders::getUrl(panel: 'app'));
        }

        // Ownership check
        $customer = Customer::where('user_id', auth()->id())->first();

        if (!$customer) {
            return redirect(ListOrders::getUrl(panel: 'app'));
        }

        /** @var ?Order $order */
        $order = Order::where('paypal_order_id', $token)
            ->where('customer_id', $customer->id)
            ->where('status', OrderStatus::Pending)
            ->first();

        if (!$order) {
            // Either doesn't exist, wrong user, or already processed
            return redirect(ListOrders::getUrl(panel: 'app'));
        }

        try {
            $captureId = $this->payPalService->captureOrder($token);
        } catch (RuntimeException $e) {
            report($e);
            AuditLog::record('paypal_capture_failed', ['error' => $e->getMessage()], $order);
            return redirect(ListOrders::getUrl(panel: 'app'));
        }

        $order->activate($captureId);
        $order->refresh();

        AuditLog::record('paypal_redirect_payment_confirmed', [
            'paypal_order_id'   => $token,
            'paypal_capture_id' => $captureId,
        ], $order);

        return $order->server
            ? redirect(Console::getUrl(panel: 'server', tenant: $order->server))
            : redirect(ListOrders::getUrl(panel: 'app'));
    }

    /**
     * PayPal redirects here when the buyer cancels.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $token = $request->get('token');

        if ($token) {
            $customer = Customer::where('user_id', auth()->id())->first();

            /** @var ?Order $order */
            $order = Order::where('paypal_order_id', $token)
                ->where('customer_id', $customer?->id)
                ->first();

            if ($order) {
                $order->close();
                AuditLog::record('paypal_checkout_cancelled', [], $order);
            }
        }

        return redirect(ListOrders::getUrl(panel: 'app'));
    }
}
