<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_expansions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('stripe_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();

            // Resource boosts applied to the server when this expansion is active
            $table->unsignedSmallInteger('cores_boost')->default(0);
            $table->unsignedInteger('memory_boost')->default(0);
            $table->unsignedInteger('disk_boost')->default(0);
            $table->integer('swap_boost')->default(0);
            $table->unsignedSmallInteger('allocation_limit_boost')->default(0);
            $table->unsignedSmallInteger('database_limit_boost')->default(0);
            $table->unsignedSmallInteger('backup_limit_boost')->default(0);

            $table->decimal('cost', 10, 2)->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('force_out_of_stock')->default(false);
            $table->unsignedInteger('stock')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_expansions');
    }
};
