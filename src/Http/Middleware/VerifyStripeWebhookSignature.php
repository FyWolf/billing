<?php

namespace Boy132\Billing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;

class VerifyStripeWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('billing.stripe.webhook_secret');

        if (empty($secret)) {
            abort(500, 'Stripe webhook secret is not configured.');
        }

        $signature = $request->header('Stripe-Signature');

        if (empty($signature)) {
            abort(400, 'Missing Stripe-Signature header.');
        }

        try {
            // Stripe requires the raw (unmodified) request body for HMAC verification.
            // Do NOT use $request->all() or json_decode here.
            $event = Webhook::constructEvent(
                $request->getContent(),
                $signature,
                $secret
            );
        } catch (SignatureVerificationException $e) {
            abort(400, 'Invalid Stripe webhook signature.');
        } catch (\UnexpectedValueException $e) {
            abort(400, 'Invalid Stripe webhook payload.');
        }

        // Attach the verified event to the request so the controller does not
        // need to re-parse or re-verify it.
        $request->attributes->set('stripe_event', $event);

        return $next($request);
    }
}
