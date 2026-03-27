<?php

namespace Fywolf\Billing\Providers;

use Fywolf\Billing\Http\Controllers\Api\CatalogController;
use Fywolf\Billing\Http\Controllers\Api\CheckoutController;
use Fywolf\Billing\Http\Controllers\Api\StripeWebhookController;
use Fywolf\Billing\Http\Middleware\VerifyStripeWebhookSignature;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Route;

class BillingRoutesProvider extends RouteServiceProvider
{
    public function boot(): void
    {
        $this->routes(function () {

            // ------------------------------------------------------------------
            // Stripe redirect callbacks (require auth — user must be logged in)
            // Rate-limited: 20 attempts per minute per user to prevent abuse
            // ------------------------------------------------------------------
            Route::prefix('checkout')
                ->middleware(['web', 'auth', 'throttle:20,1'])
                ->group(function () {
                    Route::get('/success', [CheckoutController::class, 'success'])
                        ->name('billing.checkout.success');
                    Route::get('/cancel', [CheckoutController::class, 'cancel'])
                        ->name('billing.checkout.cancel');
                });

            // ------------------------------------------------------------------
            // Public catalog API — uses Pelican's built-in API key system
            // Requires a valid Application API key (created in admin panel)
            // ------------------------------------------------------------------
            Route::get('/api/application/billing/catalog', CatalogController::class)
                ->name('billing.api.catalog')
                ->middleware(['auth:sanctum', 'throttle:60,1'])
                ->withoutMiddleware(['web', 'auth', 'verify-csrf-token', 'App\Http\Middleware\VerifyCsrfToken']);

            // ------------------------------------------------------------------
            // Stripe webhook — no auth, but HMAC signature is verified via
            // middleware before the controller processes the event
            // ------------------------------------------------------------------
            Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
                ->name('billing.webhooks.stripe')
                ->middleware(VerifyStripeWebhookSignature::class)
                ->withoutMiddleware(['auth', 'web', 'verify-csrf-token', 'App\Http\Middleware\VerifyCsrfToken']);
        });
    }
}
