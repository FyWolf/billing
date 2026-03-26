<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Products: io weight (configurable per product), trial days, soft deletes
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('io_weight')->default(500)->after('swap');
            $table->softDeletes();
        });

        // Product prices: optional free trial period
        Schema::table('product_prices', function (Blueprint $table) {
            $table->unsignedInteger('trial_days')->default(0)->after('interval_value');
        });

        // Orders: gateway tracking, PayPal fields, grace period flag, trial flag, performance indexes
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_gateway')->nullable()->after('stripe_payment_id');
            $table->string('paypal_order_id')->nullable()->after('payment_gateway');
            $table->string('paypal_capture_id')->nullable()->after('paypal_order_id');
            $table->boolean('is_trial')->default(false)->after('paypal_capture_id');
            $table->timestamp('grace_notified_at')->nullable()->after('expires_at');

            // Performance indexes for CheckOrdersCommand and ownership queries
            $table->index(['status', 'expires_at'], 'orders_status_expires_idx');
            $table->index('paypal_order_id', 'orders_paypal_order_id_idx');
        });

        // Customers: index for user lookup
        Schema::table('customers', function (Blueprint $table) {
            $table->index('user_id', 'customers_user_id_idx');
        });

        // Coupons: soft deletes
        Schema::table('coupons', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['io_weight']);
            $table->dropSoftDeletes();
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropColumn('trial_days');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_expires_idx');
            $table->dropIndex('orders_paypal_order_id_idx');
            $table->dropColumn(['payment_gateway', 'paypal_order_id', 'paypal_capture_id', 'is_trial', 'grace_notified_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_user_id_idx');
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
