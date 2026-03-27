<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('pending_price_id')->nullable()->after('product_price_id');
            $table->foreign('pending_price_id')->references('id')->on('product_prices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['pending_price_id']);
            $table->dropColumn('pending_price_id');
        });
    }
};
