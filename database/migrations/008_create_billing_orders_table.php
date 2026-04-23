<?php

use Fywolf\Billing\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('stripe_checkout_id')->nullable();
            $table->string('stripe_payment_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('payment_gateway')->nullable();
            $table->boolean('is_trial')->default(false);
            $table->string('status')->default(OrderStatus::Pending);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('grace_notified_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('confirmation_token')->nullable();
            $table->timestamp('confirmation_token_expires_at')->nullable();

            $table->unsignedInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('billing_customers')->cascadeOnDelete();

            $table->unsignedInteger('pack_price_id');
            $table->foreign('pack_price_id')->references('id')->on('billing_pack_prices')->cascadeOnDelete();

            $table->unsignedInteger('pending_pack_price_id')->nullable();
            $table->foreign('pending_pack_price_id')->references('id')->on('billing_pack_prices')->nullOnDelete();

            $table->unsignedInteger('coupon_id')->nullable();
            $table->foreign('coupon_id')->references('id')->on('billing_coupons')->nullOnDelete();

            $table->unsignedInteger('server_id')->nullable();
            $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'expires_at'], 'billing_orders_status_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_orders');
    }
};
