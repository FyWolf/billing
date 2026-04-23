<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_order_expansions', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('order_id');
            $table->foreign('order_id')->references('id')->on('billing_orders')->cascadeOnDelete();

            $table->unsignedInteger('pack_expansion_id');
            $table->foreign('pack_expansion_id')->references('id')->on('billing_pack_expansions')->cascadeOnDelete();

            // Price paid at time of purchase, stored for invoice history
            $table->decimal('price_paid', 10, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_order_expansions');
    }
};
