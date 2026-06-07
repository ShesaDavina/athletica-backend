<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_token')->nullable()->after('payment_method');
            $table->string('payment_url')->nullable()->after('payment_token');
            $table->string('order_id')->nullable()->unique()->after('payment_url');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payment_token', 'payment_url', 'order_id']);
        });
    }
};
