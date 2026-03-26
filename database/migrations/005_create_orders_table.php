<?php

use Boy132\Billing\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('stripe_checkout_id')->nullable();
            $table->string('stripe_payment_id')->nullable();
            $table->string('payment_gateway')->nullable();
            $table->string('paypal_order_id')->nullable();
            $table->string('paypal_capture_id')->nullable();
            $table->boolean('is_trial')->default(false);
            $table->string('status')->default(OrderStatus::Pending);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('grace_notified_at')->nullable();

            $table->unsignedInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();

            $table->unsignedInteger('product_price_id');
            $table->foreign('product_price_id')->references('id')->on('product_prices')->cascadeOnDelete();

            $table->unsignedInteger('server_id')->nullable();
            $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'expires_at'], 'orders_status_expires_idx');
            $table->index('paypal_order_id', 'orders_paypal_order_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
