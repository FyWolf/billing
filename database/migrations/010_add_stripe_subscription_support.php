<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('stripe_subscription_id')->nullable()->after('stripe_payment_id');

            // Drop PayPal columns
            $table->dropIndex('orders_paypal_order_id_idx');
            $table->dropColumn(['paypal_order_id', 'paypal_capture_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('stripe_subscription_id');
            $table->string('paypal_order_id')->nullable();
            $table->string('paypal_capture_id')->nullable();
            $table->index('paypal_order_id', 'orders_paypal_order_id_idx');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('stripe_customer_id');
        });
    }
};
