<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_audit_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('action');
            $table->json('metadata')->nullable();
            $table->string('ip_address')->nullable();

            $table->unsignedInteger('order_id')->nullable();
            $table->foreign('order_id')->references('id')->on('billing_orders')->nullOnDelete();

            $table->unsignedInteger('customer_id')->nullable();
            $table->foreign('customer_id')->references('id')->on('billing_customers')->nullOnDelete();

            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_audit_logs');
    }
};
