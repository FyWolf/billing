<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_coupons', function (Blueprint $table) {
            $table->increments('id');
            $table->string('stripe_coupon_id')->nullable();
            $table->string('stripe_promotion_id')->nullable();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('amount_off', 10, 2)->nullable();
            $table->unsignedTinyInteger('percent_off')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->timestamp('redeem_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_coupons');
    }
};
