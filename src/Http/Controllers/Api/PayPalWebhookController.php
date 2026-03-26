<?php

namespace Boy132\Billing\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Boy132\Billing\Enums\OrderStatus;
use Boy132\Billing\Models\AuditLog;
use Boy132\Billing\Models\Order;
use Boy132\Billing\Services\PayPalService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Handles PayPal webhook (IPN v2) events.
 *
 * Register this URL in your PayPal developer dashboard under:
 * Apps & Credentials → Your App → Webhooks
 * URL: https://your-panel.com/paypal/webhook
 *
 * Recommended events to subscribe to:
 *   PAYMENT.CAPTURE.COMPLETED
 *   PAYMENT.CAPTURE.DENIED
 *   PAYMENT.CAPTURE.REFUNDED
 */
class PayPalWebhookController extends Controller
{
    public function __construct(
        private PayPalService $payPalService
    ) {}

    public function handle(Request $request): Response
    {
        // Verify the event came from PayPal before processing
        if (!$this->payPalService->verifyWebhookSignature($request)) {
            return response('Invalid signature', 400);
        }

        $eventType = $request->input('event_type');
        $resource  = $request->input('resource', []);

        match ($eventType) {
            'PAYMENT.CAPTURE.COMPLETED' => $this->handleCaptureCompleted($resource, $request->input('id')),
            'PAYMENT.CAPTURE.DENIED'    => $this->handleCaptureDenied($resource),
            'PAYMENT.CAPTURE.REFUNDED'  => $this->handleCaptureRefunded($resource),
            default                     => null,
        };

        return response('', 200);
    }

    // -------------------------------------------------------------------------
    // Event handlers
    // -------------------------------------------------------------------------

    private function handleCaptureCompleted(array $resource, string $eventId): void
    {
        $captureId    = $resource['id'] ?? null;
        $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;

        if (!$paypalOrderId && !$captureId) {
            return;
        }

        $order = $this->findOrderByPayPal($paypalOrderId, $captureId);

        if (!$order || $order->status !== OrderStatus::Pending) {
            return;
        }

        $order->activate($captureId);

        AuditLog::record('paypal_webhook_payment_received', [
            'paypal_order_id'   => $paypalOrderId,
            'paypal_capture_id' => $captureId,
            'paypal_event_id'   => $eventId,
            'amount'            => $resource['amount'] ?? null,
        ], $order);
    }

    private function handleCaptureDenied(array $resource): void
    {
        $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;

        $order = $this->findOrderByPayPal($paypalOrderId, null);

        AuditLog::record('paypal_payment_denied', [
            'paypal_order_id' => $paypalOrderId,
            'reason'          => $resource['status_details']['reason'] ?? 'Unknown',
        ], $order);
    }

    private function handleCaptureRefunded(array $resource): void
    {
        $captureId = $resource['id'] ?? null;

        $order = Order::where('paypal_capture_id', $captureId)->first();

        AuditLog::record('paypal_payment_refunded', [
            'paypal_capture_id' => $captureId,
            'amount'            => $resource['amount'] ?? null,
        ], $order);
    }

    private function findOrderByPayPal(?string $paypalOrderId, ?string $captureId): ?Order
    {
        if ($paypalOrderId) {
            $order = Order::where('paypal_order_id', $paypalOrderId)->first();
            if ($order) {
                return $order;
            }
        }

        if ($captureId) {
            return Order::where('paypal_capture_id', $captureId)->first();
        }

        return null;
    }
}
