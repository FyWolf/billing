<?php

namespace Fywolf\Billing\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Models\AuditLog;
use Fywolf\Billing\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Event;
use Stripe\StripeClient;

/**
 * Handles verified Stripe webhook events.
 *
 * Signature verification happens in VerifyStripeWebhookSignature middleware,
 * which attaches the parsed \Stripe\Event to $request->attributes.
 *
 * Required webhook events in Stripe Dashboard:
 * - checkout.session.completed
 * - invoice.paid
 * - invoice.payment_failed
 * - customer.subscription.deleted
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        /** @var Event $event */
        $event = $request->attributes->get('stripe_event');

        match ($event->type) {
            'checkout.session.completed'    => $this->handleSessionCompleted($event->data->object),
            'invoice.paid'                  => $this->handleInvoicePaid($event->data->object),
            'invoice.payment_failed'        => $this->handleInvoicePaymentFailed($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            default                         => null,
        };

        return response('', 200);
    }

    // -------------------------------------------------------------------------
    // Event handlers
    // -------------------------------------------------------------------------

    /**
     * Initial checkout completed — activate the order.
     */
    private function handleSessionCompleted(object $session): void
    {
        if ($session->payment_status !== 'paid') {
            return;
        }

        $order = Order::where('stripe_checkout_id', $session->id)
            ->where('status', OrderStatus::Pending)
            ->first();

        if (!$order) {
            return;
        }

        $currentPeriodEnd = null;
        if ($session->subscription) {
            try {
                /** @var StripeClient $stripeClient */
                $stripeClient = app(StripeClient::class);
                $subscription = $stripeClient->subscriptions->retrieve($session->subscription);
                $currentPeriodEnd = $subscription->current_period_end;
            } catch (\Exception $e) {
                report($e);
            }
        }

        $order->activate($session->subscription, $currentPeriodEnd);

        AuditLog::record('stripe_webhook_payment_received', [
            'stripe_session_id'      => $session->id,
            'stripe_subscription_id' => $session->subscription,
            'amount_total'           => $session->amount_total,
            'currency'               => $session->currency,
        ], $order);
    }

    /**
     * Recurring invoice paid — renew the order's expiration.
     * Skips the first invoice (handled by handleSessionCompleted).
     */
    private function handleInvoicePaid(object $invoice): void
    {
        if ($invoice->billing_reason === 'subscription_create') {
            return;
        }

        if (!$invoice->subscription) {
            return;
        }

        $order = Order::where('stripe_subscription_id', $invoice->subscription)->first();

        if (!$order) {
            return;
        }

        try {
            /** @var StripeClient $stripeClient */
            $stripeClient = app(StripeClient::class);
            $subscription = $stripeClient->subscriptions->retrieve($invoice->subscription);

            $order->renew($subscription->current_period_end);

            AuditLog::record('stripe_subscription_renewed', [
                'stripe_subscription_id' => $invoice->subscription,
                'invoice_id'             => $invoice->id,
                'amount_paid'            => $invoice->amount_paid,
                'new_period_end'         => $subscription->current_period_end,
            ], $order);
        } catch (\Exception $e) {
            report($e);
        }
    }

    /**
     * Invoice payment failed — move active order to grace period.
     */
    private function handleInvoicePaymentFailed(object $invoice): void
    {
        if (!$invoice->subscription) {
            return;
        }

        $order = Order::where('stripe_subscription_id', $invoice->subscription)->first();

        if ($order && $order->status === OrderStatus::Active) {
            $order->enterGracePeriod();
        }

        AuditLog::record('stripe_invoice_payment_failed', [
            'stripe_subscription_id' => $invoice->subscription,
            'invoice_id'             => $invoice->id,
            'attempt_count'          => $invoice->attempt_count ?? null,
        ], $order);
    }

    /**
     * Subscription deleted (cancelled by Stripe after retries exhausted, or by user).
     */
    private function handleSubscriptionDeleted(object $subscription): void
    {
        $order = Order::where('stripe_subscription_id', $subscription->id)->first();

        if (!$order) {
            return;
        }

        if ($order->status === OrderStatus::Cancelled) {
            $order->close();
        } elseif (in_array($order->status, [OrderStatus::Active, OrderStatus::GracePeriod])) {
            $order->expire();
        }

        AuditLog::record('stripe_subscription_deleted', [
            'stripe_subscription_id' => $subscription->id,
            'cancellation_reason'    => $subscription->cancellation_details?->reason ?? null,
        ], $order);
    }
}
