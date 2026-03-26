<?php

namespace Boy132\Billing\Services;

use Boy132\Billing\Models\Order;
use Boy132\Billing\Models\ProductPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayPalService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $webhookId;

    public function __construct()
    {
        $mode = config('billing.paypal.mode', 'sandbox');
        $this->baseUrl      = $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        $this->clientId     = (string) config('billing.paypal.client_id');
        $this->clientSecret = (string) config('billing.paypal.secret');
        $this->webhookId    = (string) config('billing.paypal.webhook_id');
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    /**
     * Returns a cached OAuth access token. Tokens are valid for 1 hour; we
     * cache for 55 minutes to give a safe margin.
     */
    public function getAccessToken(): string
    {
        return Cache::remember('billing_paypal_access_token', 3300, function () {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post("{$this->baseUrl}/v1/oauth2/token", [
                    'grant_type' => 'client_credentials',
                ]);

            if (!$response->successful()) {
                throw new RuntimeException('PayPal: failed to obtain access token — ' . $response->body());
            }

            return $response->json('access_token');
        });
    }

    // -------------------------------------------------------------------------
    // Order management
    // -------------------------------------------------------------------------

    /**
     * Create a PayPal order for the given product price and store the PayPal
     * order ID on $order. Returns the user-facing approval URL.
     */
    public function createOrder(Order $order, ProductPrice $price): string
    {
        $response = Http::withToken($this->getAccessToken())
            ->post("{$this->baseUrl}/v2/checkout/orders", [
                'intent'          => 'CAPTURE',
                'purchase_units'  => [
                    [
                        'reference_id' => (string) $order->id,
                        'description'  => $price->product->name . ' — ' . $price->name,
                        'amount'       => [
                            'currency_code' => strtoupper(config('billing.currency', 'USD')),
                            'value'         => number_format($price->cost, 2, '.', ''),
                        ],
                    ],
                ],
                'application_context' => [
                    'brand_name'          => config('app.name'),
                    'user_action'         => 'PAY_NOW',
                    'shipping_preference' => 'NO_SHIPPING',
                    'return_url'          => route('billing.paypal.success'),
                    'cancel_url'          => route('billing.paypal.cancel'),
                ],
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('PayPal: failed to create order — ' . $response->body());
        }

        $data        = $response->json();
        $paypalOrderId = $data['id'];

        $order->update(['paypal_order_id' => $paypalOrderId]);

        $approvalLink = collect($data['links'])->firstWhere('rel', 'approve');

        if (!$approvalLink) {
            throw new RuntimeException('PayPal: approval URL not found in order response');
        }

        return $approvalLink['href'];
    }

    /**
     * Get the approval URL for an order (creates a new PayPal order if needed,
     * or re-creates when renewing/retrying). Called by Order::getPaymentUrl().
     */
    public function getApprovalUrl(Order $order): string
    {
        // Always create a fresh PayPal order — PayPal orders expire after 3 hours
        return $this->createOrder($order, $order->productPrice);
    }

    /**
     * Capture a PayPal order after the buyer approves it. Returns the capture ID.
     */
    public function captureOrder(string $paypalOrderId): string
    {
        $response = Http::withToken($this->getAccessToken())
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$this->baseUrl}/v2/checkout/orders/{$paypalOrderId}/capture");

        if (!$response->successful()) {
            throw new RuntimeException('PayPal: failed to capture order — ' . $response->body());
        }

        $data = $response->json();

        // Drill down to capture ID: purchase_units[0].payments.captures[0].id
        $captureId = $data['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;

        if (!$captureId) {
            throw new RuntimeException('PayPal: capture ID not found in response');
        }

        return $captureId;
    }

    // -------------------------------------------------------------------------
    // Webhook verification
    // -------------------------------------------------------------------------

    /**
     * Verify an incoming PayPal webhook event using PayPal's verification API.
     * Returns true when the signature is valid.
     *
     * @see https://developer.paypal.com/api/rest/webhooks/
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        if (empty($this->webhookId)) {
            // Webhook ID not configured — cannot verify; reject for safety
            return false;
        }

        $response = Http::withToken($this->getAccessToken())
            ->post("{$this->baseUrl}/v1/notifications/verify-webhook-signature", [
                'auth_algo'        => $request->header('PAYPAL-AUTH-ALGO'),
                'cert_url'         => $request->header('PAYPAL-CERT-URL'),
                'transmission_id'  => $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'transmission_time'=> $request->header('PAYPAL-TRANSMISSION-TIME'),
                'webhook_id'       => $this->webhookId,
                'webhook_event'    => $request->json()->all(),
            ]);

        if (!$response->successful()) {
            return false;
        }

        return $response->json('verification_status') === 'SUCCESS';
    }
}
