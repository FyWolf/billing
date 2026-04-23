<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_packs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('stripe_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('force_out_of_stock')->default(false);

            // Server specification
            $table->unsignedSmallInteger('cores')->default(1);
            $table->unsignedInteger('memory')->default(0);
            $table->unsignedInteger('disk')->default(0);
            $table->integer('swap')->default(0);
            $table->unsignedSmallInteger('io_weight')->default(500);

            // Port and node configuration
            $table->json('ports')->default('[]');
            $table->json('tags')->default('[]');
            $table->json('node_ids')->nullable();

            // Resource limits
            $table->unsignedSmallInteger('allocation_limit')->default(0);
            $table->unsignedSmallInteger('database_limit')->default(0);
            $table->unsignedSmallInteger('backup_limit')->default(0);

            $table->unsignedInteger('egg_id');
            $table->foreign('egg_id')->references('id')->on('eggs')->cascadeOnDelete();

            $table->unsignedInteger('product_id');
            $table->foreign('product_id')->references('id')->on('billing_products')->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_packs');
    }
};
