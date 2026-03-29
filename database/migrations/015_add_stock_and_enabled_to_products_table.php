<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('stock')->nullable()->after('sort_order')
                ->comment('Max concurrent active orders. NULL = unlimited.');
            $table->boolean('is_enabled')->default(true)->after('stock')
                ->comment('When false the product is hidden from the store and cannot be purchased.');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock', 'is_enabled']);
        });
    }
};
