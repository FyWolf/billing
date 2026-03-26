<?php

namespace Boy132\Billing\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Boy132\Billing\Enums\OrderStatus;
use Boy132\Billing\Models\AuditLog;
use Boy132\Billing\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Event;
use Stripe\StripeClient;

/**
 * Handles verified Stripe webhook events.
 *
 * Signature verification happens in VerifyStripeWebhookSignature middleware,
 * which attaches the parsed \Stripe\Event to $request->attributes.
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        /** @var Event $event */
        $event = $request->attributes->get('stripe_event');

        match ($event->type) {
            'checkout.session.completed'  => $this->handleSessionCompleted($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
            default                       => null,
        };

        return response('', 200);
    }

    // -------------------------------------------------------------------------
    // Event handlers
    // -------------------------------------------------------------------------

    private function handleSessionCompleted(object $session): void
    {
        if ($session->payment_status !== 'paid') {
            return;
        }

        $order = Order::where('stripe_checkout_id', $session->id)
            ->where('status', OrderStatus::Pending)
            ->first();

        if (!$order) {
            // Already activated (e.g. via redirect callback) or unknown session
            return;
        }

        $order->activate($session->payment_intent);

        AuditLog::record('stripe_webhook_payment_received', [
            'stripe_session_id'      => $session->id,
            'stripe_payment_intent'  => $session->payment_intent,
            'amount_total'           => $session->amount_total,
            'currency'               => $session->currency,
        ], $order);
    }

    private function handlePaymentFailed(object $paymentIntent): void
    {
        $order = null;

        try {
            /** @var StripeClient $stripeClient */
            $stripeClient = app(StripeClient::class);

            // Find the checkout session that created this payment intent
            $sessions = $stripeClient->checkout->sessions->all([
                'payment_intent' => $paymentIntent->id,
                'limit' => 1,
            ]);

            if (!empty($sessions->data)) {
                $order = Order::where('stripe_checkout_id', $sessions->data[0]->id)->first();
            }
        } catch (\Exception $e) {
            report($e);
        }

        AuditLog::record('stripe_payment_failed', [
            'payment_intent_id'    => $paymentIntent->id,
            'failure_message'      => $paymentIntent->last_payment_error?->message ?? 'Unknown',
        ], $order);
    }
}
