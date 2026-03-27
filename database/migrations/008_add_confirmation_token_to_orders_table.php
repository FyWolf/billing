<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('confirmation_token', 64)->nullable()->unique()->after('paypal_capture_id');
            $table->timestamp('confirmation_token_expires_at')->nullable()->after('confirmation_token');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['confirmation_token', 'confirmation_token_expires_at']);
        });
    }
};
