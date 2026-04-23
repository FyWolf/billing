<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_pack_expansions', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('pack_id');
            $table->foreign('pack_id')->references('id')->on('billing_packs')->cascadeOnDelete();

            $table->unsignedInteger('expansion_id');
            $table->foreign('expansion_id')->references('id')->on('billing_expansions')->cascadeOnDelete();

            // Override the expansion's base cost for this specific pack
            $table->decimal('custom_price', 10, 2)->nullable();
            $table->boolean('is_enabled')->default(true);

            $table->unique(['pack_id', 'expansion_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_pack_expansions');
    }
};
