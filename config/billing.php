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
        'name'       => env('BILLING_COMPANY_NAME', ''),
        'legal_form' => env('BILLING_COMPANY_LEGAL_FORM', ''),   // e.g. SAS, SARL, EURL
        'address'    => env('BILLING_COMPANY_ADDRESS', ''),
        'city'       => env('BILLING_COMPANY_CITY', ''),
        'country'    => env('BILLING_COMPANY_COUNTRY', ''),
        'zip'        => env('BILLING_COMPANY_ZIP', ''),
        'email'      => env('BILLING_COMPANY_EMAIL', ''),
        'phone'      => env('BILLING_COMPANY_PHONE', ''),
        'vat'        => env('BILLING_COMPANY_VAT', ''),          // Numéro TVA intracommunautaire
        'siret'      => env('BILLING_COMPANY_SIRET', ''),        // SIRET (14 digits)
        'website'    => env('BILLING_COMPANY_WEBSITE', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax / TVA Configuration
    |--------------------------------------------------------------------------
    | enabled            — whether to show a tax breakdown on invoices
    | rate               — tax rate as a decimal (0.20 = 20 % TVA in France)
    | prices_include_tax — true if your stored prices are already TTC (incl. tax),
    |                      false if they are HT (excl. tax) and tax is added on top
    | label              — label shown on invoices (TVA, VAT, GST …)
    */
    'tax' => [
        'enabled'            => (bool) env('BILLING_TAX_ENABLED', false),
        'rate'               => (float) env('BILLING_TAX_RATE', 0.20),
        'prices_include_tax' => (bool) env('BILLING_TAX_PRICES_INCLUDE_TAX', false),
        'label'              => env('BILLING_TAX_LABEL', 'TVA'),
    ],

];
