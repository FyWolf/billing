<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_packs', function (Blueprint $table) {
            $table->dropForeign(['egg_id']);
            $table->unsignedInteger('egg_id')->nullable()->change();
            $table->foreign('egg_id')->references('id')->on('eggs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('billing_packs', function (Blueprint $table) {
            $table->dropForeign(['egg_id']);
            $table->unsignedInteger('egg_id')->nullable(false)->change();
            $table->foreign('egg_id')->references('id')->on('eggs')->cascadeOnDelete();
        });
    }
};
