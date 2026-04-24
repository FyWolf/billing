<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_packs', function (Blueprint $table) {
            $table->boolean('visible_in_store')->default(true)->after('is_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('billing_packs', function (Blueprint $table) {
            $table->dropColumn('visible_in_store');
        });
    }
};
