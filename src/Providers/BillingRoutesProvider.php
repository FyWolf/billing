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
            Route::prefix('checkout')
                ->middleware(['web', 'auth', 'throttle:20,1'])
                ->group(function () {
                    Route::get('/success', [CheckoutController::class, 'success'])
                        ->name('billing.checkout.success');
                    Route::get('/cancel', [CheckoutController::class, 'cancel'])
                        ->name('billing.checkout.cancel');
                });

            Route::get('/api/application/billing/catalog', CatalogController::class)
                ->name('billing.api.catalog')
                ->middleware(['auth:sanctum', 'throttle:60,1'])
                ->withoutMiddleware(['web', 'auth', 'verify-csrf-token', 'App\Http\Middleware\VerifyCsrfToken']);

            // Webhook has no auth — HMAC signature verified in middleware before the controller runs
            Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
                ->name('billing.webhooks.stripe')
                ->middleware(VerifyStripeWebhookSignature::class)
                ->withoutMiddleware(['auth', 'web', 'verify-csrf-token', 'App\Http\Middleware\VerifyCsrfToken']);
        });
    }
}
