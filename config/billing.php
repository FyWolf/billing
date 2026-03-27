<?php

return [

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

    /*
    |--------------------------------------------------------------------------
    | Company Information (used on invoices)
    |--------------------------------------------------------------------------
    */
    'company' => [
        'name'    => env('BILLING_COMPANY_NAME', ''),
        'address' => env('BILLING_COMPANY_ADDRESS', ''),
        'city'    => env('BILLING_COMPANY_CITY', ''),
        'country' => env('BILLING_COMPANY_COUNTRY', ''),
        'zip'     => env('BILLING_COMPANY_ZIP', ''),
        'email'   => env('BILLING_COMPANY_EMAIL', ''),
        'phone'   => env('BILLING_COMPANY_PHONE', ''),
        'vat'     => env('BILLING_COMPANY_VAT', ''),
        'website' => env('BILLING_COMPANY_WEBSITE', ''),
    ],

];
