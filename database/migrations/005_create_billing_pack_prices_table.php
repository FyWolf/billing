<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_pack_prices', function (Blueprint $table) {
            $table->increments('id');
            $table->string('stripe_id')->nullable();
            $table->string('name');
            $table->decimal('cost', 10, 2)->default(0);
            $table->boolean('renewable')->default(false);
            $table->string('interval_type')->default('month');
            $table->unsignedSmallInteger('interval_value')->default(1);
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->unsignedSmallInteger('cores')->default(1);
            $table->unsignedInteger('memory')->default(1024);
            $table->unsignedInteger('disk')->default(5120);
            $table->integer('swap')->default(0);
            $table->unsignedSmallInteger('io_weight')->default(500);
            $table->unsignedSmallInteger('allocation_limit')->default(0);
            $table->unsignedSmallInteger('database_limit')->default(0);
            $table->unsignedSmallInteger('backup_limit')->default(0);
            $table->json('environment_overrides')->nullable();

            $table->unsignedInteger('pack_id');
            $table->foreign('pack_id')->references('id')->on('billing_packs')->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_pack_prices');
    }
};
