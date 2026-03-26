<?php

namespace Boy132\Billing\Providers;

use App\Models\Role;
use Boy132\Billing\Console\Commands\CheckOrdersCommand;
use Boy132\Billing\Services\PayPalService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class BillingPluginProvider extends ServiceProvider
{
    public function register(): void
    {
        // Stripe client (only registered when Stripe is configured)
        if (!empty(config('billing.stripe.secret'))) {
            $this->app->bind(StripeClient::class, fn () => new StripeClient(config('billing.stripe.secret')));
        }

        // PayPal service (singleton — holds cached access token)
        $this->app->singleton(PayPalService::class, fn () => new PayPalService());

        Role::registerCustomDefaultPermissions('customer');
        Role::registerCustomModelIcon('customer', 'tabler-user-dollar');

        Role::registerCustomDefaultPermissions('product');
        Role::registerCustomModelIcon('product', 'tabler-package');
    }

    public function boot(): void
    {
        // Log warnings for missing config instead of crashing — the admin
        // needs the panel running to access Settings → Billing and fix it.
        $this->warnMissingConfig();

        // Every 5 minutes is plenty for expiry accuracy; avoids hammering the DB
        Schedule::command(CheckOrdersCommand::class)->everyFiveMinutes()->withoutOverlapping();
    }

    private function warnMissingConfig(): void
    {
        $gateway = config('billing.active_gateway', 'stripe');

        if ($gateway === 'stripe' && empty(config('billing.stripe.secret'))) {
            Log::warning(
                'Billing plugin: STRIPE_SECRET is not set. Stripe payments will not work. '
                . 'Configure it in Settings → Billing or set the STRIPE_SECRET environment variable.'
            );
        }

        if ($gateway === 'paypal') {
            if (empty(config('billing.paypal.client_id')) || empty(config('billing.paypal.secret'))) {
                Log::warning(
                    'Billing plugin: PAYPAL_CLIENT_ID and/or PAYPAL_SECRET are not set. '
                    . 'PayPal payments will not work until configured in Settings → Billing.'
                );
            }
        }
    }
}
