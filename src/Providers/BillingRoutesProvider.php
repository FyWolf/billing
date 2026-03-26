<?php

namespace Boy132\Billing\Providers;

use Boy132\Billing\Http\Controllers\Api\CheckoutController;
use Boy132\Billing\Http\Controllers\Api\PayPalCheckoutController;
use Boy132\Billing\Http\Controllers\Api\PayPalWebhookController;
use Boy132\Billing\Http\Controllers\Api\StripeWebhookController;
use Boy132\Billing\Http\Middleware\VerifyStripeWebhookSignature;
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
                ->middleware(['auth', 'throttle:20,1'])
                ->group(function () {
                    Route::get('/success', [CheckoutController::class, 'success'])
                        ->name('billing.checkout.success');
                    Route::get('/cancel', [CheckoutController::class, 'cancel'])
                        ->name('billing.checkout.cancel');
                });

            // ------------------------------------------------------------------
            // Stripe webhook — no auth, but HMAC signature is verified via
            // middleware before the controller processes the event
            // ------------------------------------------------------------------
            Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
                ->name('billing.webhooks.stripe')
                ->middleware(VerifyStripeWebhookSignature::class)
                ->withoutMiddleware(['auth', 'web', 'verify-csrf-token', 'App\Http\Middleware\VerifyCsrfToken']);

            // ------------------------------------------------------------------
            // PayPal redirect callbacks (require auth)
            // ------------------------------------------------------------------
            Route::prefix('paypal')
                ->middleware(['auth', 'throttle:20,1'])
                ->group(function () {
                    Route::get('/success', [PayPalCheckoutController::class, 'success'])
                        ->name('billing.paypal.success');
                    Route::get('/cancel', [PayPalCheckoutController::class, 'cancel'])
                        ->name('billing.paypal.cancel');
                });

            // ------------------------------------------------------------------
            // PayPal webhook — signature verified inside the controller via
            // PayPalService::verifyWebhookSignature()
            // ------------------------------------------------------------------
            Route::post('/webhooks/paypal', [PayPalWebhookController::class, 'handle'])
                ->name('billing.webhooks.paypal')
                ->withoutMiddleware(['auth', 'web', 'verify-csrf-token', 'App\Http\Middleware\VerifyCsrfToken']);
        });
    }
}
