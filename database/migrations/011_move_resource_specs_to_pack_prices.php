<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_pack_prices', function (Blueprint $table) {
            $table->unsignedSmallInteger('cores')->default(1)->after('interval_value');
            $table->unsignedInteger('memory')->default(1024)->after('cores');
            $table->unsignedInteger('disk')->default(5120)->after('memory');
            $table->integer('swap')->default(0)->after('disk');
            $table->unsignedSmallInteger('io_weight')->default(500)->after('swap');
            $table->unsignedSmallInteger('allocation_limit')->default(0)->after('io_weight');
            $table->unsignedSmallInteger('database_limit')->default(0)->after('allocation_limit');
            $table->unsignedSmallInteger('backup_limit')->default(0)->after('database_limit');
        });

        Schema::table('billing_packs', function (Blueprint $table) {
            $table->dropColumn(['cores', 'memory', 'disk', 'swap', 'io_weight', 'allocation_limit', 'database_limit', 'backup_limit']);
        });
    }

    public function down(): void
    {
        Schema::table('billing_pack_prices', function (Blueprint $table) {
            $table->dropColumn(['cores', 'memory', 'disk', 'swap', 'io_weight', 'allocation_limit', 'database_limit', 'backup_limit']);
        });

        Schema::table('billing_packs', function (Blueprint $table) {
            $table->unsignedSmallInteger('cores')->default(1);
            $table->unsignedInteger('memory')->default(0);
            $table->unsignedInteger('disk')->default(0);
            $table->integer('swap')->default(0);
            $table->unsignedSmallInteger('io_weight')->default(500);
            $table->unsignedSmallInteger('allocation_limit')->default(0);
            $table->unsignedSmallInteger('database_limit')->default(0);
            $table->unsignedSmallInteger('backup_limit')->default(0);
        });
    }
};
