<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('last_name');
            $table->string('address')->nullable()->after('company_name');
            $table->string('address2')->nullable()->after('address');
            $table->string('city')->nullable()->after('address2');
            $table->string('zip')->nullable()->after('city');
            $table->string('country')->nullable()->after('zip');
            // EU VAT identification number (e.g. FR12345678901)
            $table->string('vat_number')->nullable()->after('country');
            // French SIRET number (14-digit company registration)
            $table->string('siret')->nullable()->after('vat_number');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'company_name', 'address', 'address2',
                'city', 'zip', 'country', 'vat_number', 'siret',
            ]);
        });
    }
};
