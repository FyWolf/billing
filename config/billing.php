<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active Payment Gateway
    |--------------------------------------------------------------------------
    | Which gateway processes new payments: 'stripe' or 'paypal'
    | Existing orders retain their original gateway value.
    */
    'active_gateway' => env('BILLING_GATEWAY', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Stripe Configuration
    |--------------------------------------------------------------------------
    */
    'stripe' => [
        'key'            => env('STRIPE_KEY'),
        'secret'         => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    // Legacy flat keys kept for backwards compatibility with EnvironmentWriterTrait
    'key'    => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | PayPal Configuration
    |--------------------------------------------------------------------------
    */
    'paypal' => [
        'client_id'    => env('PAYPAL_CLIENT_ID'),
        'secret'       => env('PAYPAL_SECRET'),
        'webhook_id'   => env('PAYPAL_WEBHOOK_ID'),
        'mode'         => env('PAYPAL_MODE', 'sandbox'), // 'sandbox' or 'live'
    ],

    /*
    |--------------------------------------------------------------------------
    | General
    |--------------------------------------------------------------------------
    */
    'currency' => env('BILLING_CURRENCY', 'USD'),

    'deployment_tags' => env('BILLING_DEPLOYMENT_TAGS'),

    /*
    |--------------------------------------------------------------------------
    | Grace Period
    |--------------------------------------------------------------------------
    | Hours a server stays online after the order expires before being
    | suspended. Set to 0 to suspend immediately on expiry.
    */
    'grace_period_hours' => (int) env('BILLING_GRACE_PERIOD_HOURS', 24),

];
