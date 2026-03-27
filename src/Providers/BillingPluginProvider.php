<?php

namespace Fywolf\Billing\Providers;

use App\Models\Role;
use Fywolf\Billing\Console\Commands\CheckOrdersCommand;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class BillingPluginProvider extends ServiceProvider
{
    public function register(): void
    {
        if (!empty(config('billing.stripe.secret'))) {
            $this->app->bind(StripeClient::class, fn () => new StripeClient(config('billing.stripe.secret')));
        }

        Role::registerCustomDefaultPermissions('customer');
        Role::registerCustomModelIcon('customer', 'tabler-user-dollar');

        Role::registerCustomDefaultPermissions('product');
        Role::registerCustomModelIcon('product', 'tabler-package');
    }

    public function boot(): void
    {
        $this->warnMissingConfig();

        Schedule::command(CheckOrdersCommand::class)->everyFiveMinutes()->withoutOverlapping();
    }

    private function warnMissingConfig(): void
    {
        if (empty(config('billing.stripe.secret'))) {
            Log::warning(
                'Billing plugin: STRIPE_SECRET is not set. Stripe payments will not work. '
                . 'Configure it in Settings → Billing or set the STRIPE_SECRET environment variable.'
            );
        }
    }
}
