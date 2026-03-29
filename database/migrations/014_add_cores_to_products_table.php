<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('cores')->default(1)->after('cpu');
        });

        // Convert existing cpu% values: 200% → 2 cores, 100% → 1 core, etc.
        DB::statement('UPDATE products SET cores = GREATEST(1, CEIL(cpu / 100))');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('cpu');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('cpu')->default(0)->after('cores');
        });

        DB::statement('UPDATE products SET cpu = cores * 100');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('cores');
        });
    }
};
